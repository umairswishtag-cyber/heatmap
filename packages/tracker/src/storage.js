const prefix = 'pulsecro:';

function readFrom(storage, key, fallback) {
  try {
    const value = storage.getItem(prefix + key);
    return value === null ? fallback : JSON.parse(value);
  } catch { return fallback; }
}

function writeTo(storage, key, value) {
  try { storage.setItem(prefix + key, JSON.stringify(value)); } catch { /* storage may be unavailable */ }
}

export function read(key, fallback = null) {
  return readFrom(globalThis.localStorage, key, fallback);
}

export function write(key, value) {
  writeTo(globalThis.localStorage, key, value);
}

export function readSession(key, fallback = null) {
  return readFrom(globalThis.sessionStorage, key, fallback);
}

export function writeSession(key, value) {
  writeTo(globalThis.sessionStorage, key, value);
}

export function remove(key) {
  try { globalThis.localStorage.removeItem(prefix + key); } catch { /* storage may be unavailable */ }
}
