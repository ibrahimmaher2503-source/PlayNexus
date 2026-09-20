import fs from 'node:fs';
import path from 'node:path';

const endpoint = process.env.MASTER_CDP_URL || 'http://127.0.0.1:8824';
let version;

for (let attempt = 0; attempt < 50; attempt += 1) {
  try {
    version = await (await fetch(`${endpoint}/json/version`)).json();
    break;
  } catch {
    await new Promise((resolve) => setTimeout(resolve, 100));
  }
}

if (!version) throw new Error('Microsoft Edge CDP did not become available.');

const root = new WebSocket(version.webSocketDebuggerUrl);
await new Promise((resolve) => root.addEventListener('open', resolve, { once: true }));
let rootId = 0;
const rootPending = new Map();
root.addEventListener('message', (event) => {
  const data = JSON.parse(event.data);
  const pending = rootPending.get(data.id);
  if (!pending) return;
  rootPending.delete(data.id);
  data.error ? pending.reject(new Error(data.error.message)) : pending.resolve(data.result);
});
const rootSend = (method, params = {}) => {
  const id = ++rootId;
  root.send(JSON.stringify({ id, method, params }));
  return new Promise((resolve, reject) => rootPending.set(id, { resolve, reject }));
};

const { browserContextId } = await rootSend('Target.createBrowserContext');
const checklist = path.resolve('.ai/checkpoints/playnexus_master_checkpoints_v3_explained (2).html');
const url = new URL(`file:///${checklist.replaceAll('\\', '/')}`).href;
const { targetId } = await rootSend('Target.createTarget', { url, browserContextId });
await new Promise((resolve) => setTimeout(resolve, 800));
const targets = await (await fetch(`${endpoint}/json/list`)).json();
const target = targets.find((item) => item.id === targetId);
if (!target) throw new Error('Checklist browser target was not created.');

const socket = new WebSocket(target.webSocketDebuggerUrl);
await new Promise((resolve) => socket.addEventListener('open', resolve, { once: true }));
let id = 0;
const pending = new Map();
socket.addEventListener('message', (event) => {
  const data = JSON.parse(event.data);
  const call = pending.get(data.id);
  if (!call) return;
  pending.delete(data.id);
  data.error ? call.reject(new Error(data.error.message)) : call.resolve(data.result);
});
const send = (method, params = {}) => {
  const callId = ++id;
  socket.send(JSON.stringify({ id: callId, method, params }));
  return new Promise((resolve, reject) => pending.set(callId, { resolve, reject }));
};

try {
  await send('Runtime.enable');
  const response = await send('Runtime.evaluate', {
    expression: `(() => {
      const expected = Object.assign({}, WAVE_01_BASELINE, WAVE_02_BASELINE);
      const expectedCounts = Object.values(expected).reduce((all, item) => {
        all[item.status] = (all[item.status] || 0) + 1;
        return all;
      }, {});
      const selected = [...document.querySelectorAll('.status-select')].reduce((all, select) => {
        all[select.value] = (all[select.value] || 0) + 1;
        return all;
      }, {});
      const wave2Mismatches = Object.entries(WAVE_02_BASELINE).filter(([key, item]) => {
        const node = document.querySelector('[data-key="' + key + '"]');
        return !node
          || !node.querySelector('.done-check').checked
          || node.querySelector('.status-select').value !== item.status
          || node.querySelector('.note-area').value !== item.note;
      }).map(([key]) => key);
      return {
        total: document.querySelectorAll('.checkpoint').length,
        checked: document.querySelectorAll('.done-check:checked').length,
        expectedChecked: Object.keys(expected).length,
        expectedCounts,
        selected,
        wave2Rows: Object.keys(WAVE_02_BASELINE).length,
        wave2Notes: [...document.querySelectorAll('.note-area')].filter((note) => note.value.startsWith('Wave 2, 2026-09-18.')).length,
        wave2Mismatches,
      };
    })()`,
    returnByValue: true,
  });
  if (response.exceptionDetails) {
    throw new Error(response.exceptionDetails.exception?.description || response.exceptionDetails.text);
  }
  const result = response.result.value;

  if (result.total !== 653) throw new Error(`Expected 653 tasks, got ${result.total}.`);
  if (result.checked !== result.expectedChecked) throw new Error(`Expected ${result.expectedChecked} checked tasks, got ${result.checked}.`);
  if (result.wave2Rows !== 119 || result.wave2Notes !== 119 || result.wave2Mismatches.length > 0) {
    throw new Error(`Wave 2 browser verification failed: ${JSON.stringify(result)}`);
  }
  for (const [status, count] of Object.entries(result.expectedCounts)) {
    if (result.selected[status] !== count) throw new Error(`${status}: expected ${count}, got ${result.selected[status] ?? 0}.`);
  }

  await send('Emulation.setDeviceMetricsOverride', { width: 1440, height: 1000, deviceScaleFactor: 1, mobile: false });
  const screenshot = await send('Page.captureScreenshot', { format: 'png' });
  const output = path.resolve('deliverables/qa/wave02/master-checklist-wave02.png');
  fs.mkdirSync(path.dirname(output), { recursive: true });
  fs.writeFileSync(output, Buffer.from(screenshot.data, 'base64'));
  console.log(JSON.stringify({ ...result, screenshot: output }, null, 2));
} finally {
  socket.close();
  await rootSend('Target.disposeBrowserContext', { browserContextId });
  root.close();
}
