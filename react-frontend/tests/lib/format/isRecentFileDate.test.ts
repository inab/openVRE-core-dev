import { describe, expect, it } from 'vitest';

import { isRecentFileDate } from '../../../src/lib/format/isRecentFileDate';

const NOW = new Date('2026-10-08T12:00:00.000Z');

describe('isRecentFileDate', () => {
  it('is true for ages under one hour', () => {
    expect(isRecentFileDate('2026-10-08T11:30:00.000Z', NOW)).toBe(true);
    expect(isRecentFileDate('2026-10-08T11:59:00.000Z', NOW)).toBe(true);
  });

  it('is false at or beyond one hour, or for future/invalid dates', () => {
    expect(isRecentFileDate('2026-10-08T11:00:00.000Z', NOW)).toBe(false);
    expect(isRecentFileDate('2026-10-08T10:00:00.000Z', NOW)).toBe(false);
    expect(isRecentFileDate('2026-10-08T13:00:00.000Z', NOW)).toBe(false);
    expect(isRecentFileDate('', NOW)).toBe(false);
    expect(isRecentFileDate('not-a-date', NOW)).toBe(false);
  });
});
