import { beforeEach, describe, expect, it, vi } from 'vitest';
import { getIdentity, SESSION_TIMEOUT_MS } from './session.js';

describe('session identity', () => {
  let sessionValues;

  beforeEach(() => {
    const localValues = new Map();
    sessionValues = new Map();
    vi.stubGlobal('localStorage', { getItem: (key) => localValues.get(key) ?? null, setItem: (key, value) => localValues.set(key, value) });
    vi.stubGlobal('sessionStorage', { getItem: (key) => sessionValues.get(key) ?? null, setItem: (key, value) => sessionValues.set(key, value) });
    vi.stubGlobal('crypto', { randomUUID: vi.fn().mockReturnValueOnce('visitor').mockReturnValueOnce('first').mockReturnValueOnce('second') });
  });

  it('keeps a visitor and rotates a session after inactivity', () => {
    const first = getIdentity(1_000);
    const active = getIdentity(2_000);
    const expired = getIdentity(2_000 + SESSION_TIMEOUT_MS + 1);
    expect(active).toEqual(first);
    expect(expired.visitorId).toBe(first.visitorId);
    expect(expired.sessionId).not.toBe(first.sessionId);
  });

  it('starts a new session for a later browser-tab visit', () => {
    const firstVisit = getIdentity(1_000);
    sessionValues.clear();
    const laterVisit = getIdentity(2_000);

    expect(laterVisit.visitorId).toBe(firstVisit.visitorId);
    expect(laterVisit.sessionId).not.toBe(firstVisit.sessionId);
  });
});
