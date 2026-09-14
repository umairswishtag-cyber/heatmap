import { getIdentity, touchSession } from './session.js';
import { Transport } from './transport.js';
import { startRecorder } from './recorder.js';
import { installClicks } from './clicks.js';
import { installScroll } from './scroll.js';
import { installNavigation } from './navigation.js';
import { installForms } from './forms.js';
import { installErrors } from './errors.js';

export function createTracker(options) {
  if (!options?.projectKey || !options?.endpoint) throw new Error('PulseCRO requires projectKey and endpoint');
  if (options.respectDnt !== false && navigator.doNotTrack === '1') return { track() {}, stop() {} };
  if (Math.random() > (options.sampleRate ?? 1)) return { track() {}, stop() {} };

  const identity = getIdentity();
  const transport = new Transport(options, identity);
  const emit = (kind, data) => { touchSession(); transport.push({ kind, data, timestamp: Date.now() }); };
  const cleanups = [
    startRecorder(emit, options.privacy || {}), installClicks(emit), installScroll(emit),
    installNavigation(emit), installForms(emit), installErrors(emit),
  ].filter(Boolean);
  emit('session_start', { url: location.href });
  emit('page_view', { url: location.href, title: document.title });
  return {
    track(name, properties = {}) { emit(name === 'conversion' ? 'conversion' : 'custom_event', { name, ...properties, url: location.href }); },
    stop() { emit('session_end', { url: location.href }); cleanups.forEach((cleanup) => cleanup()); transport.stop(); },
    identity,
  };
}

function autoStart() {
  const script = document.currentScript || document.querySelector('script[data-project-id]');
  const projectKey = script?.dataset.projectId;
  if (!projectKey || window.cro?.track) return;
  const source = new URL(script.src, location.href);
  const endpoint = script.dataset.apiUrl || `${source.origin}/graphql`;
  window.cro = createTracker({ projectKey, endpoint, sampleRate: Number(script.dataset.sampleRate || 1) });
}

if (typeof window !== 'undefined') autoStart();
