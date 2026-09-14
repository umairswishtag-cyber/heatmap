export function installScroll(emit) {
  let timer;
  let maximum = 0;
  const handler = () => {
    clearTimeout(timer);
    timer = setTimeout(() => {
      const scrollable = Math.max(1, document.documentElement.scrollHeight - innerHeight);
      const depth = Math.min(100, Math.round(scrollY * 100 / scrollable));
      if (depth >= maximum + 5 || depth === 100) {
        maximum = depth;
        emit('scroll', { depth, url: location.href });
      }
    }, 150);
  };
  addEventListener('scroll', handler, { passive: true });
  return () => { clearTimeout(timer); removeEventListener('scroll', handler); };
}
