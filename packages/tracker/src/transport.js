import { read, write } from './storage.js';

const MUTATION = `mutation Ingest($input: RecordingBatchInput!) { ingestRecording(input: $input) { accepted batchId eventCount } }`;

async function encode(events) {
  const json = JSON.stringify(events);
  if (typeof CompressionStream === 'undefined') return { encoding: 'JSON', payload: json };
  const stream = new Blob([json]).stream().pipeThrough(new CompressionStream('gzip'));
  const bytes = new Uint8Array(await new Response(stream).arrayBuffer());
  let binary = '';
  for (let offset = 0; offset < bytes.length; offset += 0x8000) binary += String.fromCharCode(...bytes.subarray(offset, offset + 0x8000));
  return { encoding: 'GZIP_BASE64', payload: btoa(binary) };
}

export class Transport {
  constructor(config, identity) {
    this.config = config; this.identity = identity; this.buffer = []; this.flushing = false;
    this.pending = read('pending_batches', []); this.timer = setInterval(() => this.flush(), config.flushInterval || 5000);
    addEventListener('online', () => this.flush());
    addEventListener('pagehide', () => this.flush(true));
    if (this.pending.length) queueMicrotask(() => this.flush());
  }

  push(event) {
    this.buffer.push(event);
    if (this.buffer.length >= 300) this.flush();
  }

  async flush(beacon = false) {
    if (this.flushing || (!this.buffer.length && !this.pending.length)) return;
    this.flushing = true;
    const events = this.pending.shift() || this.buffer.splice(0);
    write('pending_batches', this.pending);
    try {
      const encoded = beacon ? { encoding: 'JSON', payload: JSON.stringify(events) } : await encode(events);
      const body = JSON.stringify({ query: MUTATION, variables: { input: {
        projectKey: this.config.projectKey, visitorId: this.identity.visitorId, sessionId: this.identity.sessionId,
        url: location.href, referrer: document.referrer || null, viewportWidth: innerWidth, viewportHeight: innerHeight,
        userAgent: navigator.userAgent, ...encoded,
      } } });
      if (beacon && navigator.sendBeacon?.(this.config.endpoint, new Blob([body], { type: 'application/json' }))) return;
      const response = await fetch(this.config.endpoint, { method: 'POST', headers: { 'content-type': 'application/json' }, body, keepalive: beacon });
      const result = await response.json();
      if (!response.ok || result.errors?.length || !result.data?.ingestRecording?.accepted) throw new Error('Batch rejected');
    } catch {
      this.pending.unshift(events);
      this.pending = this.pending.slice(0, 20);
      write('pending_batches', this.pending);
    } finally { this.flushing = false; }
  }

  stop() { clearInterval(this.timer); this.flush(true); }
}
