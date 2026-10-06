import { TriangleAlert } from 'lucide-react';

import './FileKindIndicator.css';

export const UnavailableIndicator = () => (
  <span
    className="fileKindIndicator fileKindIndicatorUnavailable"
    title="Not available on disk"
  >
    <TriangleAlert
      aria-label="Not available on disk"
      className="fileKindIndicatorIcon"
      fill="var(--color-text)"
      size={14}
      strokeWidth={2}
    />
  </span>
);
