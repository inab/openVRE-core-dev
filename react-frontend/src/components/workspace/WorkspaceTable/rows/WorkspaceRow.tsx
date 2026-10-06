import {
  ChevronDown,
  ChevronRight,
  File,
  Folder,
  FolderOpen,
} from 'lucide-react';

import type { FileItem } from '../../../../lib/workspace/FileItem';
import {
  isFolderKind,
  isUnavailableKind,
  isUnvalidatedKind,
} from '../../../../lib/workspace/fileItemKind';

import { UnavailableIndicator } from '../indicators/UnavailableIndicator';
import { UnvalidatedIndicator } from '../indicators/UnvalidatedIndicator';

import './WorkspaceRow.css';

export interface WorkspaceRowNameProps {
  item: FileItem;
  depth: number;
  canExpand: boolean;
  isExpanded: boolean;
  onToggleExpand: () => void;
}

interface KindIconProps {
  kind: FileItem['kind'];
  isExpanded: boolean;
}

const KindIcon = ({ kind, isExpanded }: KindIconProps) => {
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
};

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
  const showUnavailable = isUnavailableKind(item.kind);
  const showUnvalidated = isUnvalidatedKind(item.kind);

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
      {showUnavailable ? <UnavailableIndicator /> : null}
      {showUnvalidated && !showUnavailable ? <UnvalidatedIndicator /> : null}
    </div>
  );
};
