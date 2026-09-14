import { isIgnored, safeSelector } from './privacy.js';

export function installClicks(emit) {
  const handler = (event) => {
    if (isIgnored(event.target)) return;
    emit('click', {
      x: Math.round(event.clientX), y: Math.round(event.clientY),
      viewportWidth: innerWidth, viewportHeight: innerHeight,
      selector: safeSelector(event.target), url: location.href,
    });
  };
  document.addEventListener('click', handler, { capture: true, passive: true });
  return () => document.removeEventListener('click', handler, true);
}
