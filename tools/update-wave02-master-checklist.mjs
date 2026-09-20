import fs from 'node:fs';
import path from 'node:path';

const root = process.cwd();
const evidencePath = path.join(root, '.ai', 'waves', '02-FAMILIES-PRIVACY-PRICING-TICKETS.md');
const checklistPath = path.join(root, '.ai', 'checkpoints', 'playnexus_master_checkpoints_v3_explained (2).html');

const statusValues = new Map([
  ['منفذ', 'implemented'],
  ['جزئي', 'partial'],
  ['ناقص', 'missing'],
  ['لا يعمل', 'broken'],
  ['يحتاج UI', 'ui'],
  ['محجوب بقرار', 'blocked'],
  ['مؤجل', 'deferred'],
  ['Production Ready', 'production'],
]);

const normalize = (value) => value.trim().toLocaleLowerCase('ar');
const decodeHtml = (value) => value
  .replaceAll('&amp;', '&')
  .replaceAll('&lt;', '<')
  .replaceAll('&gt;', '>')
  .replaceAll('&quot;', '"')
  .replaceAll('&#39;', "'");

const evidence = fs.readFileSync(evidencePath, 'utf8');
const mapping = evidence
  .split('## Master HTML Task Mapping')[1]
  ?.split('## Status Counts')[0];

if (!mapping) {
  throw new Error('Wave 2 mapping section was not found.');
}

const rows = mapping.split(/\r?\n/).flatMap((line) => {
  const cells = line.split('|').slice(1, -1).map((cell) => cell.trim());

  if (cells.length !== 5 || !statusValues.has(cells[2])) {
    return [];
  }

  const prefix = cells[0].match(/^(08|09|10|11|28|32|33|34|35|36|41|42)$/)?.[1];

  return prefix ? [{
    prefix,
    task: cells[1],
    status: statusValues.get(cells[2]),
    evidence: cells[3],
    gap: cells[4],
  }] : [];
});

if (rows.length !== 119) {
  throw new Error(`Expected 119 Wave 2 evidence rows, found ${rows.length}.`);
}

let html = fs.readFileSync(checklistPath, 'utf8');
const checkpoints = new Map();
const checkpointPattern = /<div class="checkpoint" data-key="([^"]+)"[\s\S]*?<span class="cp-text">([\s\S]*?)<\/span>/g;

for (const match of html.matchAll(checkpointPattern)) {
  checkpoints.set(`${match[1].split('-')[0]}:${normalize(decodeHtml(match[2]))}`, match[1]);
}

const baseline = {};
const missing = [];

for (const row of rows) {
  const key = checkpoints.get(`${row.prefix}:${normalize(row.task)}`);

  if (!key) {
    missing.push(`${row.prefix}: ${row.task}`);
    continue;
  }

  baseline[key] = {
    checked: true,
    status: row.status,
    note: `Wave 2, 2026-09-18. Evidence: ${row.evidence}. Remaining gap: ${row.gap}.`,
  };
}

if (missing.length > 0 || Object.keys(baseline).length !== 119) {
  throw new Error(`Checklist mapping failed:\n${missing.join('\n')}`);
}

const counts = Object.values(baseline).reduce((result, item) => {
  result[item.status] = (result[item.status] ?? 0) + 1;
  return result;
}, {});

const expectedCounts = { implemented: 110, partial: 6, blocked: 2, deferred: 1 };
for (const [status, expected] of Object.entries(expectedCounts)) {
  if (counts[status] !== expected) {
    throw new Error(`${status}: expected ${expected}, got ${counts[status] ?? 0}.`);
  }
}

const begin = '// BEGIN WAVE 02 AUTHORITATIVE BASELINE';
const end = '// END WAVE 02 AUTHORITATIVE BASELINE';
const baselineCode = `${begin}
const WAVE_02_BASELINE_REVISION='2026-09-18-families-privacy-pricing-tickets-v1';
const WAVE_02_BASELINE_STORE='playnexus_master_applied_baselines';
const WAVE_02_BASELINE=${JSON.stringify(baseline, null, 2)};
function applyWave02Baseline(state){
  let applied=[];
  try{applied=JSON.parse(localStorage.getItem(WAVE_02_BASELINE_STORE)||'[]')}catch(e){}
  if(!applied.includes(WAVE_02_BASELINE_REVISION)){
    Object.assign(state,WAVE_02_BASELINE);
    localStorage.setItem(STORE,JSON.stringify(state));
    applied.push(WAVE_02_BASELINE_REVISION);
    localStorage.setItem(WAVE_02_BASELINE_STORE,JSON.stringify(applied));
  }
  return state;
}
${end}`;

const existingPattern = new RegExp(`${begin}[\\s\\S]*?${end}`);

if (existingPattern.test(html)) {
  html = html.replace(existingPattern, baselineCode);
} else {
  const anchor = '// END WAVE 01 AUTHORITATIVE BASELINE';
  if (!html.includes(anchor)) {
    throw new Error('Could not find the Wave 1 baseline anchor.');
  }
  html = html.replace(anchor, `${anchor}\n${baselineCode}`);
}

const stateLoad = 's=applyWave01Baseline(s);';
const patchedStateLoad = `${stateLoad}\n  s=applyWave02Baseline(s);`;

if (!html.includes('s=applyWave02Baseline(s);')) {
  if (!html.includes(stateLoad)) {
    throw new Error('Could not find the Wave 1 state-load anchor.');
  }
  html = html.replace(stateLoad, patchedStateLoad);
}

fs.writeFileSync(checklistPath, html, 'utf8');

console.log(JSON.stringify({
  checklist: checklistPath,
  tasksUpdated: Object.keys(baseline).length,
  counts,
}, null, 2));
