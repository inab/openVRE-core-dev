import { describe, expect, it } from 'vitest';

import { formatFileDate } from '../../../src/lib/format/formatFileDate';

describe('formatFileDate', () => {
  it('returns a dotted local date-time', () => {
    expect(formatFileDate('2026-07-17T11:41:19.000+00:00')).toMatch(
      /^\d{4}\.\d{1,2}\.\d{1,2} \d{2}:\d{2}$/,
    );
  });
});
