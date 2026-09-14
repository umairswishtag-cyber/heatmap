const sensitiveTypes = new Set(['password', 'email', 'tel']);

export function isIgnored(element) {
  return Boolean(element?.closest?.('[data-cro-ignore]'));
}

export function safeSelector(element) {
  if (!element || isIgnored(element)) return '';
  const id = element.id && !/password|token|secret/i.test(element.id) ? `#${CSS.escape(element.id)}` : '';
  return id || [element.tagName?.toLowerCase(), ...Array.from(element.classList || []).slice(0, 2).map((name) => `.${CSS.escape(name)}`)].join('');
}

export function recorderPrivacyOptions(settings = {}) {
  return {
    maskAllInputs: settings.maskInputs !== false,
    maskTextSelector: '[data-cro-mask], input, textarea, [contenteditable="true"]',
    blockSelector: '[data-cro-ignore], input[type="password"], [autocomplete="cc-number"], [autocomplete="cc-csc"]',
    ignoreClass: 'cro-ignore',
  };
}

export function safeFormMetadata(element) {
  if (!element || isIgnored(element)) return null;
  return {
    type: sensitiveTypes.has(element.type) ? 'sensitive' : (element.type || element.tagName?.toLowerCase()),
    name: /password|token|secret|card|cvv/i.test(element.name || '') ? 'masked' : (element.name || ''),
  };
}
