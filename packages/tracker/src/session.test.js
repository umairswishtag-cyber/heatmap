import { beforeEach, describe, expect, it, vi } from 'vitest';
import { getIdentity, SESSION_TIMEOUT_MS } from './session.js';

describe('session identity', () => {
  beforeEach(() => {
    const values = new Map();
    vi.stubGlobal('localStorage', { getItem: (key) => values.get(key) ?? null, setItem: (key, value) => values.set(key, value) });
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
});
