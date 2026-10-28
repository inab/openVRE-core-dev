import { afterEach, describe, expect, it, vi } from 'vitest';

import {
  openLegacyFileMetadataModal,
  toLegacyMetadataModalType,
} from '../../../src/lib/legacy/openLegacyFileMetadataModal';
import { FILE_ITEM_KINDS } from '../../../src/types/fileItemConstants';

afterEach(() => {
  vi.unstubAllGlobals();
  vi.restoreAllMocks();
});

describe('toLegacyMetadataModalType', () => {
  it('maps folders to 0 and files to 1', () => {
    expect(
      toLegacyMetadataModalType({ kind: FILE_ITEM_KINDS.folder }),
    ).toBe(0);
    expect(
      toLegacyMetadataModalType({ kind: FILE_ITEM_KINDS.folder_unavailable }),
    ).toBe(0);
    expect(toLegacyMetadataModalType({ kind: FILE_ITEM_KINDS.file })).toBe(1);
    expect(
      toLegacyMetadataModalType({ kind: FILE_ITEM_KINDS.file_unavailable }),
    ).toBe(1);
  });
});

describe('openLegacyFileMetadataModal', () => {
  it('returns false when viewFileMeta is missing', () => {
    vi.stubGlobal('window', {});
    expect(
      openLegacyFileMetadataModal({
        fileId: 'f1',
        filename: 'a.txt',
        kind: FILE_ITEM_KINDS.file,
      }),
    ).toBe(false);
  });

  it('calls viewFileMeta with file type 1', () => {
    const viewFileMeta = vi.fn();
    vi.stubGlobal('window', { viewFileMeta });

    expect(
      openLegacyFileMetadataModal({
        fileId: 'f1',
        filename: 'a.txt',
        kind: FILE_ITEM_KINDS.file_unvalidated,
      }),
    ).toBe(true);
    expect(viewFileMeta).toHaveBeenCalledWith('f1', 'a.txt', 1);
  });
});
