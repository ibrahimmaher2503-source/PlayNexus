import fs from 'node:fs';
import path from 'node:path';

const endpoint = 'http://127.0.0.1:8823';
let version;

for (let attempt = 0; attempt < 30; attempt += 1) {
  try {
    version = await (await fetch(`${endpoint}/json/version`)).json();
    break;
  } catch {
    await new Promise((resolve) => setTimeout(resolve, 100));
  }
}

if (!version) {
  throw new Error('Microsoft Edge CDP did not become available.');
}

const browserSocket = new WebSocket(version.webSocketDebuggerUrl);
await new Promise((resolve) => browserSocket.addEventListener('open', resolve, { once: true }));
let browserId = 0;
const browserPending = new Map();
browserSocket.addEventListener('message', (event) => {
  const data = JSON.parse(event.data);
  const pending = browserPending.get(data.id);
  if (!pending) return;
  browserPending.delete(data.id);
  data.error ? pending.reject(new Error(data.error.message)) : pending.resolve(data.result);
});
const browserSend = (method, params = {}) => {
  const id = ++browserId;
  browserSocket.send(JSON.stringify({ id, method, params }));
  return new Promise((resolve, reject) => browserPending.set(id, { resolve, reject }));
};

const { browserContextId } = await browserSend('Target.createBrowserContext');
const checklist = path.resolve('.ai/checkpoints/playnexus_master_checkpoints_v3_explained (2).html');
const url = new URL(`file:///${checklist.replaceAll('\\', '/')}`).href;
const { targetId } = await browserSend('Target.createTarget', { url, browserContextId });
await new Promise((resolve) => setTimeout(resolve, 700));
const targets = await (await fetch(`${endpoint}/json/list`)).json();
const target = targets.find((item) => item.id === targetId);

if (!target) {
  throw new Error('Checklist browser target was not created.');
}

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
const evaluate = async (expression) => {
  const result = await send('Runtime.evaluate', { expression, returnByValue: true });
  if (result.exceptionDetails) throw new Error(result.exceptionDetails.text);
  return result.result.value;
};

try {
  await send('Page.enable');
  const result = await evaluate(`(() => ({
    total: document.getElementById('total')?.textContent,
    checked: document.getElementById('checked')?.textContent,
    implemented: document.getElementById('implemented')?.textContent,
    issues: document.getElementById('issues')?.textContent,
    percent: document.getElementById('percent')?.textContent,
    selected: [...document.querySelectorAll('.status-select')].reduce((counts, select) => {
      counts[select.value] = (counts[select.value] || 0) + 1;
      return counts;
    }, {}),
    checkedInputs: document.querySelectorAll('.done-check:checked').length,
    waveNotes: [...document.querySelectorAll('.note-area')].filter((note) => note.value.startsWith('Wave 1, 2026-09-17.')).length,
  }))()`);

  const expected = {
    total: '653',
    checked: '153',
    implemented: '127',
    issues: '23',
    percent: '23%',
    checkedInputs: 153,
    waveNotes: 153,
  };

  for (const [field, value] of Object.entries(expected)) {
    if (result[field] !== value) {
      throw new Error(`${field}: expected ${value}, got ${result[field]}`);
    }
  }

  if (result.selected.implemented !== 127 || result.selected.partial !== 23 || result.selected.blocked !== 3 || result.selected.not_reviewed !== 500) {
    throw new Error(`Unexpected browser status counts: ${JSON.stringify(result.selected)}`);
  }

  await send('Emulation.setDeviceMetricsOverride', { width: 1440, height: 1000, deviceScaleFactor: 1, mobile: false });
  const screenshot = await send('Page.captureScreenshot', { format: 'png' });
  const output = path.resolve('deliverables/qa/wave-01/master-checklist-wave01.png');
  fs.mkdirSync(path.dirname(output), { recursive: true });
  fs.writeFileSync(output, Buffer.from(screenshot.data, 'base64'));
  console.log(JSON.stringify({ ...result, screenshot: output }, null, 2));
} finally {
  socket.close();
  await browserSend('Target.disposeBrowserContext', { browserContextId });
  browserSocket.close();
}
