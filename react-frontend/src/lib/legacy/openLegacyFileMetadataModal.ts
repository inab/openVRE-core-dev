/**
 * TEMPORARY bridge to the legacy metadata modal (`#modalMeta`).
 * Delete when React owns the metadata modal. See README → Legacy jQuery bridges.
 */
import type { ApiFileItem } from '../../types/ApiFileItem';
import { isFolderKind } from '../workspace/fileItemKind';

declare global {
  interface Window {
    viewFileMeta?: (id: string, name: string, type: 0 | 1) => void;
  }
}

/** jQuery `viewFileMeta` type: folder → 0, file → 1. */
export function toLegacyMetadataModalType(
  item: Pick<ApiFileItem, 'kind'>,
): 0 | 1 {
  return isFolderKind(item.kind) ? 0 : 1;
}

export function openLegacyFileMetadataModal(
  item: Pick<ApiFileItem, 'fileId' | 'filename' | 'kind'>,
): boolean {
  const viewFileMeta = globalThis.window?.viewFileMeta;
  if (typeof viewFileMeta !== 'function') {
    return false;
  }

  viewFileMeta(item.fileId, item.filename, toLegacyMetadataModalType(item));
  return true;
}
