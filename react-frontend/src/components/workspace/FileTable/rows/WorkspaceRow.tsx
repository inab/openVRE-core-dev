import {
  ChevronDown,
  ChevronRight,
  File,
  Folder,
  FolderOpen,
} from 'lucide-react';

import { FILE_ITEM_KINDS } from '../../../../types/fileItemConstants';
import type { FileItem } from '../types/FileItem';

import './WorkspaceRow.css';

export interface WorkspaceRowNameProps {
  item: FileItem;
  depth: number;
  canExpand: boolean;
  isExpanded: boolean;
  onToggleExpand: () => void;
}

function isFolderKind(kind: FileItem['kind']): boolean {
  return (
    kind === FILE_ITEM_KINDS.folder ||
    kind === FILE_ITEM_KINDS.folder_empty ||
    kind === FILE_ITEM_KINDS.folder_uploads ||
    kind === FILE_ITEM_KINDS.folder_repository
  );
}

function KindIcon({
  kind,
  isExpanded,
}: {
  kind: FileItem['kind'];
  isExpanded: boolean;
}) {
  if (isFolderKind(kind)) {
    if (isExpanded) {
      return (
        <FolderOpen
          aria-hidden
          className="workspaceRowIcon workspaceRowIconFolder workspaceRowIconFolderOpen"
          size={16}
        />
      );
    }

    return (
      <Folder
        aria-hidden
        className="workspaceRowIcon workspaceRowIconFolder"
        fill="currentColor"
        size={16}
      />
    );
  }

  return (
    <File
      aria-hidden
      className="workspaceRowIcon workspaceRowIconFile"
      size={16}
    />
  );
}

export const WorkspaceRowName = ({
  item,
  depth,
  canExpand,
  isExpanded,
  onToggleExpand,
}: WorkspaceRowNameProps) => {
  const isFolder = isFolderKind(item.kind);
  const nameClass = isFolder
    ? 'workspaceRowName workspaceRowNameFolder'
    : 'workspaceRowName';

  return (
    <div
      className={nameClass}
      style={{ paddingLeft: `${depth * 16}px` }}
    >
      {canExpand ? (
        <button
          type="button"
          className="workspaceRowExpand"
          aria-expanded={isExpanded}
          aria-label={
            isExpanded ? `Collapse ${item.filename}` : `Expand ${item.filename}`
          }
          onClick={(event) => {
            event.stopPropagation();
            onToggleExpand();
          }}
        >
          {isExpanded ? (
            <ChevronDown
              aria-hidden
              size={14}
            />
          ) : (
            <ChevronRight
              aria-hidden
              size={14}
            />
          )}
        </button>
      ) : (
        <span
          aria-hidden
          className="workspaceRowExpandSpacer"
        />
      )}
      <KindIcon
        kind={item.kind}
        isExpanded={canExpand && isExpanded}
      />
      <span
        className="workspaceRowFilename"
        title={item.path}
      >
        {item.filename}
      </span>
      {item.status === 'unvalidated' ? (
        <span className="workspaceRowBadge">Unvalidated</span>
      ) : null}
    </div>
  );
};
