#!/usr/bin/env node
// Claude Review — DeepSeek V4-Flash (primary) / Haiku 4.5 (Fallback) liest Diff,
// postet Kommentar auf PR oder Commit. Läuft in Forgejo Actions. Kein State, kein Server.
//
// Strategie-B (28.04.2026): DeepSeek primary weil ~9x billiger ($0.0006 vs $0.0055/Review)
// und Quality vergleichbar (HumanEval 93% vs 88%, LiveCodeBench 58% vs 35%).
// Haiku als Fallback bei DeepSeek-Outage. Bypass-Anthropic spart auch die Lock-Latency
// wenn Anthropic-Quota erreicht ist.
import Anthropic from '@anthropic-ai/sdk';
import { execSync } from 'node:child_process';

const MODEL = 'claude-haiku-4-5-20251001';
const DEEPSEEK_MODEL = 'deepseek-chat';
const MAX_DIFF_CHARS = 40000;
const MAX_OUTPUT_TOKENS = 600;
const INCLUDE_PATTERNS = ['*.js', '*.mjs', '*.cjs', '*.ts', '*.tsx', '*.jsx', '*.json', '*.yml', '*.yaml', '*.sh'];
const EXCLUDE_PATTERNS = [':(exclude)**/node_modules/**', ':(exclude)**/dist/**', ':(exclude)**/build/**', ':(exclude)**/*.min.js', ':(exclude)package-lock.json', ':(exclude)yarn.lock', ':(exclude)pnpm-lock.yaml'];

const { EVENT_NAME, REPO, PR_NUMBER, HEAD_SHA, BASE_SHA, GITEA_TOKEN, FORGEJO_URL } = process.env;
const FORGEJO_BASE = (FORGEJO_URL || 'https://forgejo.kurvenschule.cloud').replace(/\/$/, '');

function sh(cmd, opts = {}) {
  return execSync(cmd, { encoding: 'utf8', ...opts });
}

function getDiff() {
  const paths = [...INCLUDE_PATTERNS.map(p => `'${p}'`), ...EXCLUDE_PATTERNS.map(p => `'${p}'`)].join(' ');
  let base = BASE_SHA;
  if (!base || base === '0000000000000000000000000000000000000000') {
    try { base = sh(`git rev-parse ${HEAD_SHA}^`).trim(); } catch { base = null; }
  }
  if (!base) return { diff: '', stat: 'Initial commit — kein Base' };
  // fetch-depth: 0 im Workflow-Checkout sorgt für vollständige History — kein expliziter fetch nötig.
  const stat = sh(`git diff --stat ${base} ${HEAD_SHA} -- ${paths}`).trim();
  const diff = sh(`git diff ${base} ${HEAD_SHA} -- ${paths}`);
  return { diff, stat, base };
}

const SYSTEM_PROMPT = `Du bist Code-Reviewer für einen Solo-Entwickler. Deine Aufgabe: schnelles, konkretes Feedback auf Diffs.

Fokus (in dieser Reihenfolge):
1. **Security**: Injection (SQL/Command), Path Traversal, fehlende Auth, hardcoded Secrets, CSRF, XSS, unsichere RegEx, fehlende Input-Validation
2. **Logik-Bugs**: Race Conditions, falsche Nullable-Handling, off-by-one, Error-Swallowing, fehlende Transactions
3. **Scope-Creep**: Code der nicht zum Commit-Zweck passt, Refactoring-Schleichen, unnötige Abstraktionen
4. **Regressions-Risiko**: Breaking Changes in API/Schema ohne Migration, entfernte Validierung, Rate-Limits weg

IGNORIERE:
- Stil/Formatting (hat Linter)
- Teststrukturen (außer fehlende Tests für Security-Bugs)
- TypeScript-Strictness
- Kommentare/Docs
- Performance-Micro-Optimierungen

Antwort-Format (Markdown, max 200 Wörter):
## 🤖 Claude Review

**Severity:** OK | LOW | MEDIUM | HIGH | CRITICAL

### Findings
(Leer lassen wenn nichts. Sonst Bullet-Points mit Datei:Zeile und konkretem Problem.)

### Zusammenfassung
(Ein Satz: Was passiert im Diff, ist es sauber gemacht?)

Wenn alles OK: "Severity: OK" + einzelner Satz + keine Findings. Nicht freundlich plaudern.`;

// DeepSeek V4-Flash via raw HTTPS (kein SDK noetig, kein extra dep).
async function reviewDeepSeek(userPrompt, truncated) {
  if (!process.env.DEEPSEEK_API_KEY) throw new Error('DEEPSEEK_API_KEY secret fehlt');
  const payload = JSON.stringify({
    model: DEEPSEEK_MODEL,
    messages: [
      { role: 'system', content: SYSTEM_PROMPT },
      { role: 'user', content: userPrompt }
    ],
    temperature: 0.2,
    max_tokens: MAX_OUTPUT_TOKENS,
  });
  const res = await fetch('https://api.deepseek.com/v1/chat/completions', {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      'Authorization': `Bearer ${process.env.DEEPSEEK_API_KEY}`,
    },
    body: payload,
  });
  if (!res.ok) {
    const body = await res.text().catch(() => '');
    throw new Error(`DeepSeek HTTP ${res.status}: ${body.substring(0, 200)}`);
  }
  const data = await res.json();
  if (data.error) throw new Error(`DeepSeek API: ${data.error.message || JSON.stringify(data.error)}`);
  const msg = data.choices && data.choices[0] && data.choices[0].message;
  const u = data.usage || {};
  const text = (msg && msg.content) || '';
  const cache = u.prompt_cache_hit_tokens || 0;
  const footer = `\n\n---\n<sub>🤖 deepseek-v4-flash · in=${u.prompt_tokens}${cache ? `/cache=${cache}` : ''} out=${u.completion_tokens}${truncated ? ' · ⚠️ Diff abgeschnitten' : ''}</sub>`;
  return text + footer;
}

// Anthropic-Aufruf (Fallback). System-Prompt mit ephemeral cache.
async function reviewAnthropic(userPrompt, truncated) {
  if (!process.env.ANTHROPIC_API_KEY) throw new Error('ANTHROPIC_API_KEY fehlt');
  const client = new Anthropic();
  const msg = await client.messages.create({
    model: MODEL,
    max_tokens: MAX_OUTPUT_TOKENS,
    system: [{ type: 'text', text: SYSTEM_PROMPT, cache_control: { type: 'ephemeral' } }],
    messages: [{ role: 'user', content: userPrompt }],
  });
  const text = msg.content.map(b => b.type === 'text' ? b.text : '').join('');
  const usage = msg.usage || {};
  const footer = `\n\n---\n<sub>🤖 claude-haiku-4-5 (fallback) · in=${usage.input_tokens}${usage.cache_read_input_tokens ? `/cache=${usage.cache_read_input_tokens}` : ''} out=${usage.output_tokens}${truncated ? ' · ⚠️ Diff abgeschnitten' : ''}</sub>`;
  return text + footer;
}

async function review(diff, stat, eventName, repoName) {
  const truncated = diff.length > MAX_DIFF_CHARS;
  const diffForPrompt = truncated ? diff.slice(0, MAX_DIFF_CHARS) + '\n\n[... Diff abgeschnitten ...]' : diff;
  const userPrompt = `Repo: ${repoName}\nEvent: ${eventName}\nDiffstat:\n${stat}\n\nDiff:\n\`\`\`diff\n${diffForPrompt}\n\`\`\``;

  // Strategie-B (28.04.2026): DeepSeek primary (cost-optimiert, ~9x billiger),
  // Haiku Fallback bei DeepSeek-Outage (Resilience).
  if (!process.env.DEEPSEEK_API_KEY && !process.env.ANTHROPIC_API_KEY) {
    throw new Error('Weder DEEPSEEK_API_KEY noch ANTHROPIC_API_KEY gesetzt');
  }
  if (!process.env.DEEPSEEK_API_KEY) {
    console.warn('DEEPSEEK_API_KEY fehlt — direkt Anthropic-Fallback');
    return reviewAnthropic(userPrompt, truncated);
  }

  try {
    return await reviewDeepSeek(userPrompt, truncated);
  } catch (err) {
    if (!process.env.ANTHROPIC_API_KEY) throw err;
    console.warn(`DeepSeek-Fehler (${err.message}) — Haiku-Fallback`);
    return reviewAnthropic(userPrompt, truncated);
  }
}

async function postComment(body) {
  if (!GITEA_TOKEN) {
    console.warn('GITEA_TOKEN fehlt — Kommentar wird nicht gepostet');
    return;
  }
  const [owner, repo] = (REPO || '').split('/');
  if (EVENT_NAME === 'pull_request' && PR_NUMBER) {
    const url = `${FORGEJO_BASE}/api/v1/repos/${owner}/${repo}/issues/${PR_NUMBER}/comments`;
    const res = await fetch(url, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Authorization': `token ${GITEA_TOKEN}`,
      },
      body: JSON.stringify({ body }),
    });
    if (!res.ok) {
      const text = await res.text().catch(() => '');
      throw new Error(`Forgejo comment HTTP ${res.status}: ${text.substring(0, 200)}`);
    }
  } else {
    // Push-Event: Forgejo hat keine direkte Commit-Kommentar-API wie GitHub.
    // Review-Body nur in den Action-Log schreiben.
    console.log('Push-Event: Forgejo-Commit-Kommentar nicht unterstützt — nur Log.');
  }
}

(async () => {
  const { diff, stat } = getDiff();
  if (!diff.trim()) {
    console.log('Kein relevanter Code-Diff — skip.');
    return;
  }
  console.log('Diffstat:\n' + stat);
  console.log(`Diff-Länge: ${diff.length} Zeichen`);
  const body = await review(diff, stat, EVENT_NAME, REPO);
  console.log('\n--- Review ---\n' + body);
  await postComment(body);
  console.log('\n✅ Review gepostet.');
})().catch(e => {
  console.error('❌ Claude Review Fehler:', e.message);
  if (e.stack) console.error(e.stack);
  // exit 1: Action-Job wird als failed angezeigt (sichtbar im PR-Check).
  // Blockiert nichts, weil die Action nicht als required check konfiguriert ist.
  process.exitCode = 1;
});
