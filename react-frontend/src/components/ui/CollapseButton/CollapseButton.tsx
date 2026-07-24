import { ChevronDown } from 'lucide-react';
import { CollapseButtonProps } from './CollapseButtonProps';

import './CollapseButton.css';

export function CollapseButton({ isCollapsed, onClick }: CollapseButtonProps) {
  const label = isCollapsed ? 'Expand' : 'Collapse';

  return (
    <button
      type="button"
      className="collapseButton hasTooltip"
      aria-expanded={!isCollapsed}
      aria-label={label}
      data-tooltip={label}
      onClick={onClick}
    >
      <ChevronDown
        className={`collapseButtonIcon${isCollapsed ? ' collapseButtonIcon--collapsed' : ''}`}
      />
    </button>
  );
}
