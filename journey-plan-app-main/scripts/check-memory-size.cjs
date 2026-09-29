#!/usr/bin/env node
/**
 * MEMORY.md size gate.
 *
 * MEMORY.md is loaded in full at the start of every Claude session, so its size
 * is a direct tax on every future session. Past 100 KB it must be trimmed:
 * move the OLDEST decision-log entries into docs/memory-archive/ and leave the
 * current phase, roadmap, next-up, open questions and known issues in place.
 *
 * Usage:
 *   node scripts/check-memory-size.cjs        # report, exit 0 unless over
 *   node scripts/check-memory-size.cjs --quiet # only print when action needed
 */
const { statSync, readFileSync, readdirSync } = require('node:fs');
const { join } = require('node:path');

const LIMIT_BYTES = 100 * 1024;
const WARN_AT = 0.85; // start nudging before the hard limit

const root = join(__dirname, '..');
const memoryPath = join(root, 'MEMORY.md');
const archiveDir = join(root, 'docs', 'memory-archive');

const quiet = process.argv.includes('--quiet');
const size = statSync(memoryPath).size;
const pct = size / LIMIT_BYTES;
const kb = (n) => `${(n / 1024).toFixed(1)} KB`;

// Date-stamp the oldest decision-log entry so the archiving step has a
// concrete starting point rather than "some old entries".
function oldestEntryDate() {
  const text = readFileSync(memoryPath, 'utf-8');
  const dates = [...text.matchAll(/^### (\d{4}-\d{2}-\d{2})/gm)].map((m) => m[1]).sort();
  return dates.length > 0 ? dates[0] : null;
}

if (size >= LIMIT_BYTES) {
  const oldest = oldestEntryDate();
  const existing = readdirSync(archiveDir).filter((f) => f.endsWith('.md'));
  console.error(
    [
      `MEMORY.md is ${kb(size)} — at or over the ${kb(LIMIT_BYTES)} limit. Trim it before ending this session.`,
      '',
      'How:',
      `  1. Cut the oldest Decision log entries (they start at ${oldest ?? 'the top of the log'})`,
      `     until MEMORY.md is comfortably under ${kb(LIMIT_BYTES)} — aim for ~70 KB.`,
      `  2. Paste them verbatim into docs/memory-archive/decision-log-<first>-to-<last>.md`,
      `     (${existing.length} archive file(s) already there — follow the same naming).`,
      '  3. Keep in MEMORY.md: Current phase, Roadmap, Next up, Open questions,',
      '     Known issues, the session-end template, and the most recent entries.',
      '  4. Leave a pointer line in the Decision log noting what moved and where.',
      '',
      'Nothing is deleted — archived entries stay in the repo and in git history.',
    ].join('\n'),
  );
  process.exit(1);
}

if (!quiet || pct >= WARN_AT) {
  const verdict = pct >= WARN_AT ? 'approaching the limit — plan a trim' : 'ok';
  console.log(
    `MEMORY.md ${kb(size)} / ${kb(LIMIT_BYTES)} (${(pct * 100).toFixed(0)}%) — ${verdict}`,
  );
}
