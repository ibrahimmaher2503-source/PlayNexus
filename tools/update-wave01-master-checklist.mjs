import fs from 'node:fs';
import path from 'node:path';

const root = process.cwd();
const evidencePath = path.join(root, '.ai', 'waves', '01-FOUNDATION-ACCESS-CONFIG.md');
const checklistPath = path.join(
  root,
  '.ai',
  'checkpoints',
  'playnexus_master_checkpoints_v3_explained (2).html',
);

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
const rows = evidence.split(/\r?\n/).flatMap((line) => {
  const cells = line.split('|').slice(1, -1).map((cell) => cell.trim());

  if (cells.length !== 5 || !statusValues.has(cells[2])) {
    return [];
  }

  const prefix = cells[0].match(/^(\d{2})\b/)?.[1];

  return prefix ? [{
    prefix,
    task: cells[1],
    status: statusValues.get(cells[2]),
    evidence: cells[3],
    gap: cells[4],
  }] : [];
});

if (rows.length !== 153) {
  throw new Error(`Expected 153 Wave 1 evidence rows, found ${rows.length}.`);
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
    note: `Wave 1, 2026-09-17. Evidence: ${row.evidence}. Remaining gap: ${row.gap}.`,
  };
}

if (missing.length > 0 || Object.keys(baseline).length !== 153) {
  throw new Error(`Checklist mapping failed:\n${missing.join('\n')}`);
}

const counts = Object.values(baseline).reduce((result, item) => {
  result[item.status] = (result[item.status] ?? 0) + 1;
  return result;
}, {});

if (counts.implemented !== 127 || counts.partial !== 23 || counts.blocked !== 3) {
  throw new Error(`Unexpected status counts: ${JSON.stringify(counts)}`);
}

const begin = '// BEGIN WAVE 01 AUTHORITATIVE BASELINE';
const end = '// END WAVE 01 AUTHORITATIVE BASELINE';
const baselineCode = `${begin}
const WAVE_01_BASELINE_REVISION='2026-09-17-foundation-access-config-v1';
const WAVE_01_BASELINE_STORE='playnexus_master_applied_baselines';
const WAVE_01_BASELINE=${JSON.stringify(baseline, null, 2)};
function applyWave01Baseline(state){
  let applied=[];
  try{applied=JSON.parse(localStorage.getItem(WAVE_01_BASELINE_STORE)||'[]')}catch(e){}
  if(!applied.includes(WAVE_01_BASELINE_REVISION)){
    Object.assign(state,WAVE_01_BASELINE);
    localStorage.setItem(STORE,JSON.stringify(state));
    applied.push(WAVE_01_BASELINE_REVISION);
    localStorage.setItem(WAVE_01_BASELINE_STORE,JSON.stringify(applied));
  }
  return state;
}
${end}`;

const existingPattern = new RegExp(`${begin}[\\s\\S]*?${end}`);

if (existingPattern.test(html)) {
  html = html.replace(existingPattern, baselineCode);
} else {
  const anchor = "const UI_STORE='playnexus_master_checkpoints_v3_ui';";

  if (!html.includes(anchor)) {
    throw new Error('Could not find checklist state-store anchor.');
  }

  html = html.replace(anchor, `${anchor}\n${baselineCode}`);
}

const stateLoad = "try{s=JSON.parse(localStorage.getItem(STORE)||'{}')}catch(e){}";
const patchedStateLoad = `${stateLoad}\n  s=applyWave01Baseline(s);`;

if (!html.includes('s=applyWave01Baseline(s);')) {
  if (!html.includes(stateLoad)) {
    throw new Error('Could not find checklist load-state anchor.');
  }

  html = html.replace(stateLoad, patchedStateLoad);
}

fs.writeFileSync(checklistPath, html, 'utf8');

console.log(JSON.stringify({
  checklist: checklistPath,
  tasksUpdated: Object.keys(baseline).length,
  counts,
}, null, 2));
