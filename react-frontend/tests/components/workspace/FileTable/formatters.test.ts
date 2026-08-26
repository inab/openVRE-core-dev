import { describe, expect, it } from 'vitest';

import {
  formatFileDate,
  formatFileSize,
  formatShowingEntries,
} from '../../../../src/components/workspace/FileTable/formatters';

describe('formatFileSize', () => {
  it('formats common sizes', () => {
    expect(formatFileSize(0)).toBe('0 B');
    expect(formatFileSize(1024)).toBe('1 KB');
  });
});

describe('formatFileDate', () => {
  it('returns a dotted local date-time', () => {
    expect(formatFileDate('2026-07-17T11:41:19.000+00:00')).toMatch(
      /^\d{4}\.\d{1,2}\.\d{1,2} \d{2}:\d{2}$/,
    );
  });
});

describe('formatShowingEntries', () => {
  it('shows the current page window', () => {
    expect(formatShowingEntries(0, 50, 72)).toBe(
      'Showing 1 to 50 of 72 entries',
    );
  });
});
