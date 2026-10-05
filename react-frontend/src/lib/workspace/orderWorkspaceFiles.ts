import type { ApiFileItem } from '../../types/ApiFileItem';
import { FILE_ITEM_KINDS, FILE_TYPES } from '../../types/fileItemConstants';

function compareFilename(
  a: Pick<ApiFileItem, 'filename'>,
  b: Pick<ApiFileItem, 'filename'>,
): number {
  return a.filename.localeCompare(b.filename, undefined, {
    sensitivity: 'base',
    numeric: true,
  });
}

/** Oldest first; filename as a stable tiebreaker. */
function compareDateAsc(
  a: Pick<ApiFileItem, 'date' | 'filename'>,
  b: Pick<ApiFileItem, 'date' | 'filename'>,
): number {
  const byDate = a.date.localeCompare(b.date);
  if (byDate !== 0) {
    return byDate;
  }
  return compareFilename(a, b);
}

function isDirectory(item: Pick<ApiFileItem, 'type'>): boolean {
  return item.type === FILE_TYPES.dir;
}

function topLevelRootRank(kind: ApiFileItem['kind']): number {
  if (kind === FILE_ITEM_KINDS.folder_uploads) {
    return 0;
  }
  if (kind === FILE_ITEM_KINDS.folder_repository) {
    return 1;
  }
  return 2;
}

function compareTopLevelRoots(
  a: Pick<ApiFileItem, 'filename' | 'kind'>,
  b: Pick<ApiFileItem, 'filename' | 'kind'>,
): number {
  const rankDiff = topLevelRootRank(a.kind) - topLevelRootRank(b.kind);
  if (rankDiff !== 0) {
    return rankDiff;
  }
  return compareFilename(a, b);
}

/** Folders by name; files by date (newest first). Folders before files. */
function compareSiblings(a: ApiFileItem, b: ApiFileItem): number {
  const aDir = isDirectory(a);
  const bDir = isDirectory(b);
  if (aDir !== bDir) {
    return aDir ? -1 : 1;
  }
  if (aDir) {
    return compareFilename(a, b);
  }
  return compareDateAsc(a, b);
}

/**
 * Flat list order: uploads folder (+ subtree), then repository, then other
 * roots by filename. Within a folder, nested dirs by name and files by date
 * (oldest first), depth-first.
 */
export function orderWorkspaceFiles(files: ApiFileItem[]): ApiFileItem[] {
  const byId = new Map(files.map((file) => [file.fileId, file]));
  const childrenByParent = new Map<string, ApiFileItem[]>();
  const roots: ApiFileItem[] = [];

  for (const file of files) {
    if (file.parentId != null && byId.has(file.parentId)) {
      const siblings = childrenByParent.get(file.parentId);
      if (siblings) {
        siblings.push(file);
      } else {
        childrenByParent.set(file.parentId, [file]);
      }
    } else {
      roots.push(file);
    }
  }

  for (const siblings of childrenByParent.values()) {
    siblings.sort(compareSiblings);
  }
  roots.sort(compareTopLevelRoots);

  const ordered: ApiFileItem[] = [];

  const walk = (node: ApiFileItem): void => {
    ordered.push(node);
    for (const child of childrenByParent.get(node.fileId) ?? []) {
      walk(child);
    }
  };

  for (const root of roots) {
    walk(root);
  }

  return ordered;
}
