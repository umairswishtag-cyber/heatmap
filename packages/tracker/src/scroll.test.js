import { describe, expect, it } from 'vitest';
import { calculateScrollDepth } from './scroll.js';

describe('scroll depth', () => {
  it('measures the deepest visible content instead of the scrollbar position', () => {
    expect(calculateScrollDepth(0, 800, 2_000)).toBe(40);
    expect(calculateScrollDepth(700, 800, 2_000)).toBe(75);
    expect(calculateScrollDepth(1_200, 800, 2_000)).toBe(100);
  });

  it('reports a short page as fully viewed', () => {
    expect(calculateScrollDepth(0, 900, 700)).toBe(100);
  });
});
