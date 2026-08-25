import type { ApiFileItem } from '../../../../types/ApiFileItem';
import type { FileItem } from '../types/FileItem';

/**
 * Nest a flat ApiFileItem page into UI roots using parentId → children[].
 * Only links when the parent is on this page; otherwise the row is a page root.
 * Payload fields (kind, status, actions, flags) are left unchanged.
 */
export function adaptFilesPage(files: ApiFileItem[]): FileItem[] {
  const byId = new Map<string, FileItem>();

  for (const file of files) {
    byId.set(file.fileId, { ...file, children: [] });
  }

  const roots: FileItem[] = [];

  for (const node of byId.values()) {
    const parent = node.parentId != null ? byId.get(node.parentId) : undefined;
    if (parent) {
      parent.children.push(node);
    } else {
      roots.push(node);
    }
  }

  return roots;
}
