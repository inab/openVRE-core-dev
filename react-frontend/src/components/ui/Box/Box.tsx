import { useState } from 'react';
import { BoxProps } from './BoxProps';
import { CollapseButton } from '../CollapseButton/CollapseButton';

import './Box.css';

export function Box({
  title,
  subtitle,
  headerComponent,
  isCollapsable,
  children,
  footer,
}: BoxProps) {
  const [isCollapsed, setIsCollapsed] = useState(Boolean(isCollapsable));

  return (
    <div className="boxContainer">
      <div className="boxHeader">
        <div className="boxTitleContainer">
          {title && <h3 className="boxTitle">{title}</h3>}
          {subtitle && <p className="boxSubtitle">{subtitle}</p>}
        </div>
        {isCollapsable && (
          <CollapseButton
            isCollapsed={isCollapsed}
            onClick={() => setIsCollapsed((collapsed) => !collapsed)}
          />
        )}
        {headerComponent}
      </div>
      {!(isCollapsable && isCollapsed) && (
        <>
          <div className="boxContent">{children}</div>
          {footer && <div className="boxFooter">{footer}</div>}
        </>
      )}
    </div>
  );
}
