export function installNavigation(emit) {
  const notify = () => queueMicrotask(() => emit('page_view', { url: location.href, title: document.title }));
  const originalPush = history.pushState;
  const originalReplace = history.replaceState;
  history.pushState = function (...args) { originalPush.apply(this, args); notify(); };
  history.replaceState = function (...args) { originalReplace.apply(this, args); notify(); };
  addEventListener('popstate', notify);
  return () => {
    history.pushState = originalPush; history.replaceState = originalReplace;
    removeEventListener('popstate', notify);
  };
}
