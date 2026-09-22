import { describe, expect, it } from 'vitest';
import { isRageCluster } from './clicks.js';

describe('frustration signal classification', () => {
  it('recognizes three clicks on the same nearby target as a rage cluster', () => {
    const current = { selector: '#buy', x: 100, y: 100 };
    const clicks = [
      { selector: '#buy', x: 80, y: 90 },
      { selector: '#buy', x: 105, y: 102 },
      current,
    ];

    expect(isRageCluster(clicks, current)).toBe(true);
    expect(isRageCluster([...clicks.slice(0, 2), { selector: '#menu', x: 100, y: 100 }], current)).toBe(false);
  });
});
