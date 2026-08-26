import { describe, expect, it } from 'vitest';

import { formatShowingEntries } from '../../../../src/components/workspace/WorkspaceTable/formatShowingEntries';

describe('formatShowingEntries', () => {
  it('shows the current page window', () => {
    expect(formatShowingEntries(0, 50, 72)).toBe(
      'Showing 1 to 50 of 72 entries',
    );
  });
});
