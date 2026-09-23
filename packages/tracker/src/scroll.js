export function calculateScrollDepth(scrollTop, viewportHeight, documentHeight) {
  return Math.min(100, Math.max(0, Math.round(
    (Math.max(0, scrollTop) + Math.max(0, viewportHeight)) * 100 / Math.max(1, documentHeight),
  )));
}

export function installScroll(emit) {
  let timer;
  let maximum = 0;
  const handler = () => {
    clearTimeout(timer);
    timer = setTimeout(() => {
      const documentHeight = Math.max(document.documentElement.scrollHeight, document.body?.scrollHeight || 0);
      const depth = calculateScrollDepth(scrollY, innerHeight, documentHeight);
      if (depth >= maximum + 5 || depth === 100) {
        maximum = depth;
        emit('scroll', { depth, url: location.href });
      }
    }, 150);
  };
  addEventListener('scroll', handler, { passive: true });
  addEventListener('resize', handler, { passive: true });
  if (document.readyState === 'complete') handler();
  else addEventListener('load', handler, { once: true });

  return () => {
    clearTimeout(timer);
    removeEventListener('scroll', handler);
    removeEventListener('resize', handler);
    removeEventListener('load', handler);
  };
}
