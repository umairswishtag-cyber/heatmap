const prefix = 'pulsecro:';

export function read(key, fallback = null) {
  try {
    const value = localStorage.getItem(prefix + key);
    return value === null ? fallback : JSON.parse(value);
  } catch { return fallback; }
}

export function write(key, value) {
  try { localStorage.setItem(prefix + key, JSON.stringify(value)); } catch { /* storage may be unavailable */ }
}

export function remove(key) {
  try { localStorage.removeItem(prefix + key); } catch { /* storage may be unavailable */ }
}
