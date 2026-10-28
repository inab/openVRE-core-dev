import {
  FILE_ITEM_ACTIONS,
  FILE_ITEM_STATUSES,
  FOLDER_KINDS,
  UNAVAILABLE_KINDS,
  UNVALIDATED_KINDS,
  type FileItemAction,
  type FileItemKind,
  type FileItemStatus,
  type FolderKind,
  type UnavailableKind,
  type UnvalidatedKind,
} from '../../types/fileItemConstants';

const FILE_ITEM_ACTION_VALUES: readonly string[] =
  Object.values(FILE_ITEM_ACTIONS);

export function isUnvalidatedKind(kind: FileItemKind): kind is UnvalidatedKind {
  return (UNVALIDATED_KINDS as readonly FileItemKind[]).includes(kind);
}

export function isUnavailableKind(kind: FileItemKind): kind is UnavailableKind {
  return (UNAVAILABLE_KINDS as readonly FileItemKind[]).includes(kind);
}

export function isFolderKind(kind: FileItemKind): kind is FolderKind {
  return (FOLDER_KINDS as readonly FileItemKind[]).includes(kind);
}

export function isUnvalidatedStatus(status: FileItemStatus): boolean {
  return status === FILE_ITEM_STATUSES.unvalidated;
}

export function isFileItemAction(value: string): value is FileItemAction {
  return FILE_ITEM_ACTION_VALUES.includes(value);
}

/** Keep known API actions; skip values the UI does not yet handle. */
export function resolveFileItemActions(item: {
  actions: readonly string[];
}): FileItemAction[] {
  return item.actions.filter(isFileItemAction);
}
