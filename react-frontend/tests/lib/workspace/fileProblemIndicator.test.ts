import { describe, expect, it } from 'vitest';

import {
  DISK_UNAVAILABLE_LABEL,
  fileProblemLabel,
  fileProblemTooltip,
  VALIDATE_METADATA_LABEL,
} from '../../../src/lib/workspace/fileProblemIndicator';
import {
  FILE_ITEM_KINDS,
  FILE_ITEM_STATUSES,
} from '../../../src/types/fileItemConstants';

describe('fileProblemTooltip', () => {
  it('is hidden when neither problem applies', () => {
    expect(fileProblemTooltip(false, false)).toBeNull();
  });

  it('names disk-only, metadata-only, and both problems', () => {
    expect(fileProblemTooltip(true, false)).toBe(DISK_UNAVAILABLE_LABEL);
    expect(fileProblemTooltip(false, true)).toBe(VALIDATE_METADATA_LABEL);
    expect(fileProblemTooltip(true, true)).toBe(
      `${DISK_UNAVAILABLE_LABEL}. ${VALIDATE_METADATA_LABEL}.`,
    );
  });
});

describe('fileProblemLabel', () => {
  it('uses kind for disk and status for metadata', () => {
    expect(
      fileProblemLabel({
        kind: FILE_ITEM_KINDS.file,
        status: FILE_ITEM_STATUSES.ready,
      }),
    ).toBeNull();

    expect(
      fileProblemLabel({
        kind: FILE_ITEM_KINDS.file_unavailable,
        status: FILE_ITEM_STATUSES.ready,
      }),
    ).toBe(DISK_UNAVAILABLE_LABEL);

    expect(
      fileProblemLabel({
        kind: FILE_ITEM_KINDS.file,
        status: FILE_ITEM_STATUSES.unvalidated,
      }),
    ).toBe(VALIDATE_METADATA_LABEL);

    expect(
      fileProblemLabel({
        kind: FILE_ITEM_KINDS.file_unavailable,
        status: FILE_ITEM_STATUSES.unvalidated,
      }),
    ).toBe(`${DISK_UNAVAILABLE_LABEL}. ${VALIDATE_METADATA_LABEL}.`);

    expect(
      fileProblemLabel({
        kind: FILE_ITEM_KINDS.file_unvalidated,
        status: FILE_ITEM_STATUSES.ready,
      }),
    ).toBeNull();
  });
});
