import { TriangleAlert } from 'lucide-react';
import type { MouseEvent } from 'react';

import { openLegacyFileMetadataModal } from '../../../../lib/legacy/openLegacyFileMetadataModal';
import type { FileItem } from '../../../../lib/workspace/FileItem';
import { fileProblemLabel } from '../../../../lib/workspace/fileProblemIndicator';

import './FileKindIndicator.css';

export interface FileProblemIndicatorProps {
  item: FileItem;
}

export const FileProblemIndicator = ({ item }: FileProblemIndicatorProps) => {
  const label = fileProblemLabel(item);
  if (label == null) {
    return null;
  }

  const handleClick = (event: MouseEvent<HTMLButtonElement>) => {
    event.stopPropagation();
    openLegacyFileMetadataModal(item);
  };

  return (
    <button
      type="button"
      className="fileKindIndicator fileKindIndicatorButton"
      title={label}
      aria-label={label}
      onClick={handleClick}
    >
      <TriangleAlert
        aria-hidden
        className="fileKindIndicatorIcon"
        fill="var(--color-text)"
        size={14}
        strokeWidth={2}
      />
    </button>
  );
};
