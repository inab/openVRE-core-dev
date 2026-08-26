import type { ApiFileItem } from '../../types/ApiFileItem';
import { FILE_ITEM_KINDS } from '../../types/fileItemConstants';

function compareFilename(
  a: Pick<ApiFileItem, 'filename'>,
  b: Pick<ApiFileItem, 'filename'>,
): number {
  return a.filename.localeCompare(b.filename, undefined, {
    sensitivity: 'base',
    numeric: true,
  });
}

function compareTopLevelRoots(
  a: Pick<ApiFileItem, 'filename' | 'kind'>,
  b: Pick<ApiFileItem, 'filename' | 'kind'>,
): number {
  const aUploads = a.kind === FILE_ITEM_KINDS.folder_uploads;
  const bUploads = b.kind === FILE_ITEM_KINDS.folder_uploads;
  if (aUploads !== bUploads) {
    return aUploads ? -1 : 1;
  }
  return compareFilename(a, b);
}

/**
 * Flat list order: uploads folder (+ subtree) first, then other roots by
 * filename with each subtree depth-first. Used by adaptFilesPage after
 * client-side filter/search.
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
    siblings.sort(compareFilename);
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
