import { read, readSession, write, writeSession } from './storage.js';

export const SESSION_TIMEOUT_MS = 30 * 60 * 1000;

export function identifier(prefix) {
  const uuid = globalThis.crypto?.randomUUID?.() ?? `${Date.now().toString(36)}${Math.random().toString(36).slice(2)}`;
  return `${prefix}_${uuid}`;
}

export function getIdentity(now = Date.now()) {
  const visitorId = read('visitor_id') || identifier('visitor');
  const previous = readSession('session');
  const session = !previous || now - previous.lastActivity > SESSION_TIMEOUT_MS
    ? { id: identifier('session'), startedAt: now, lastActivity: now }
    : { ...previous, lastActivity: now };
  write('visitor_id', visitorId);
  writeSession('session', session);
  return { visitorId, sessionId: session.id };
}

export function touchSession(now = Date.now()) {
  const session = readSession('session');
  if (session) writeSession('session', { ...session, lastActivity: now });
}
