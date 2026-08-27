import type { ApiFileItem } from '../../types/ApiFileItem';
import { FILE_TYPES } from '../../types/fileItemConstants';

/**
 * Case-insensitive match on path or filename.
 * Keeps only matching nodes (no ancestors) so hits appear as table roots —
 * e.g. searching "sim" shows the CSV files, not the repository folder.
 */
export function filterFilesBySearch(
  files: ApiFileItem[],
  query: string,
): ApiFileItem[] {
  const q = query.trim().toLowerCase();
  if (!q) {
    return files;
  }

  return files.filter(
    (file) =>
      file.path.toLowerCase().includes(q) ||
      file.filename.toLowerCase().includes(q),
  );
}

/**
 * Keep files whose dataType is in the tool's list, plus ancestor folders so
 * the tree still nests. No tool (`null`) leaves the list unchanged.
 */
export function filterFilesByTool(
  files: ApiFileItem[],
  dataTypes: readonly string[] | null,
): ApiFileItem[] {
  if (dataTypes == null) {
    return files;
  }

  if (dataTypes.length === 0) {
    return [];
  }

  const accepted = new Set(dataTypes);
  const byId = new Map(files.map((file) => [file.fileId, file]));
  const keep = new Set<string>();

  for (const file of files) {
    if (file.type === FILE_TYPES.dir || !accepted.has(file.dataType)) {
      continue;
    }

    let current: ApiFileItem | undefined = file;
    while (current && !keep.has(current.fileId)) {
      keep.add(current.fileId);
      current =
        current.parentId != null ? byId.get(current.parentId) : undefined;
    }
  }

  return files.filter((file) => keep.has(file.fileId));
}
