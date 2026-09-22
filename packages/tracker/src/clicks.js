import { isIgnored, safeSelector } from './privacy.js';

export function isRageCluster(clicks, current) {
  return clicks.filter((click) => click.selector === current.selector
    && Math.abs(click.x - current.x) <= 40 && Math.abs(click.y - current.y) <= 40).length >= 3;
}

export function installClicks(emit) {
  let recentClicks = [];
  const pendingChecks = new Set();

  const handler = (event) => {
    if (isIgnored(event.target)) return;
    const payload = {
      x: Math.round(event.clientX), y: Math.round(event.clientY),
      pageX: Math.round(event.pageX), pageY: Math.round(event.pageY),
      viewportWidth: innerWidth, viewportHeight: innerHeight,
      documentHeight: Math.max(document.documentElement.scrollHeight, document.body?.scrollHeight || 0),
      selector: safeSelector(event.target), url: location.href,
    };
    emit('click', payload);

    const now = Date.now();
    recentClicks = recentClicks.filter((click) => now - click.time <= 2_000);
    recentClicks.push({ time: now, x: payload.x, y: payload.y, selector: payload.selector });
    if (isRageCluster(recentClicks, payload)) {
      emit('rage_click', payload);
      recentClicks = [];
    }

    const target = event.target;
    const tag = target?.tagName?.toLowerCase();
    const hasExpectedNativeResponse = ['input', 'select', 'textarea', 'label', 'summary'].includes(tag);
    const looksClickable = target?.closest?.('button,a,[role="button"],[onclick]')
      || globalThis.getComputedStyle?.(target)?.cursor === 'pointer';
    if (!looksClickable || hasExpectedNativeResponse || typeof MutationObserver === 'undefined') return;

    const initialUrl = location.href;
    let changed = false;
    const observer = new MutationObserver(() => { changed = true; });
    observer.observe(document.documentElement, { subtree: true, childList: true, attributes: true });
    const check = { observer, timer: null };
    check.timer = setTimeout(() => {
      observer.disconnect();
      pendingChecks.delete(check);
      if (!changed && location.href === initialUrl) emit('dead_click', payload);
    }, 1_500);
    pendingChecks.add(check);
  };
  document.addEventListener('click', handler, { capture: true, passive: true });
  return () => {
    document.removeEventListener('click', handler, true);
    pendingChecks.forEach(({ observer, timer }) => { observer.disconnect(); clearTimeout(timer); });
    pendingChecks.clear();
  };
}
