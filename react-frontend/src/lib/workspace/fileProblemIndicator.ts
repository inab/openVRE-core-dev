import type {
  FileItemKind,
  FileItemStatus,
} from '../../types/fileItemConstants';
import { isUnavailableKind, isUnvalidatedStatus } from './fileItemKind';

export const DISK_UNAVAILABLE_LABEL = 'Not available on disk';
export const VALIDATE_METADATA_LABEL = 'Validate file metadata';

export function fileProblemTooltip(
  unavailable: boolean,
  unvalidated: boolean,
): string | null {
  if (unavailable && unvalidated) {
    return `${DISK_UNAVAILABLE_LABEL}. ${VALIDATE_METADATA_LABEL}.`;
  }
  if (unavailable) {
    return DISK_UNAVAILABLE_LABEL;
  }
  if (unvalidated) {
    return VALIDATE_METADATA_LABEL;
  }

  return null;
}

export function fileProblemLabel(item: {
  kind: FileItemKind;
  status: FileItemStatus;
}): string | null {
  return fileProblemTooltip(
    isUnavailableKind(item.kind),
    isUnvalidatedStatus(item.status),
  );
}
