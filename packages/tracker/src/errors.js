export function installErrors(emit) {
  const error = (event) => emit('javascript_error', { message: String(event.message || 'Unknown error').slice(0, 500), source: event.filename || '', line: event.lineno || 0, url: location.href });
  const rejection = (event) => emit('javascript_error', { message: String(event.reason?.message || event.reason || 'Unhandled rejection').slice(0, 500), url: location.href });
  addEventListener('error', error); addEventListener('unhandledrejection', rejection);
  return () => { removeEventListener('error', error); removeEventListener('unhandledrejection', rejection); };
}
