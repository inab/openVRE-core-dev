import { TriangleAlert } from 'lucide-react';

import './FileKindIndicator.css';

export const UnvalidatedIndicator = () => (
  <span
    className="fileKindIndicator fileKindIndicatorUnvalidated"
    title="Validate file metadata"
  >
    <TriangleAlert
      aria-label="Validate file metadata"
      className="fileKindIndicatorIcon"
      fill="var(--color-text)"
      size={14}
      strokeWidth={2}
    />
  </span>
);
