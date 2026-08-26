import type { ApiFileItem } from '../../types/ApiFileItem';

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
