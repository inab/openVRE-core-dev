import { describe, expect, it } from 'vitest';

import { formatFileSize } from '../../../src/lib/format/formatFileSize';

describe('formatFileSize', () => {
  it('formats common sizes', () => {
    expect(formatFileSize(0)).toBe('0 B');
    expect(formatFileSize(1024)).toBe('1 KB');
  });
});
