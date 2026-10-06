import {
  ACTIONS_BY_KIND,
  FOLDER_KINDS,
  UNAVAILABLE_KINDS,
  UNVALIDATED_KINDS,
  type FileItemAction,
  type FileItemKind,
  type FolderKind,
  type UnavailableKind,
  type UnvalidatedKind,
} from '../../types/fileItemConstants';

export function isUnvalidatedKind(kind: FileItemKind): kind is UnvalidatedKind {
  return (UNVALIDATED_KINDS as readonly FileItemKind[]).includes(kind);
}

export function isUnavailableKind(kind: FileItemKind): kind is UnavailableKind {
  return (UNAVAILABLE_KINDS as readonly FileItemKind[]).includes(kind);
}

export function isFolderKind(kind: FileItemKind): kind is FolderKind {
  return (FOLDER_KINDS as readonly FileItemKind[]).includes(kind);
}

/** Prefer API `actions` when present; otherwise defaults from `kind`. */
export function resolveFileItemActions(item: {
  kind: FileItemKind;
  actions?: readonly FileItemAction[];
}): readonly FileItemAction[] {
  return item.actions ?? ACTIONS_BY_KIND[item.kind];
}
