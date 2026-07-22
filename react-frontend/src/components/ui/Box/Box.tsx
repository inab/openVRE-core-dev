import css from './Box.css?inline';
import { injectCss } from '../../../lib/inject-css';
import { BoxProps } from './BoxProps';

injectCss('box-styles', css);

export function Box({
  title,
  subtitle,
  headerComponent,
  children,
  footer,
}: BoxProps) {
  return (
    <div className="boxContainer">
      <div className="boxHeader">
        <div className="boxTitleContainer">
          {title && <h3 className="boxTitle">{title}</h3>}
          {subtitle && <p className="boxSubtitle">{subtitle}</p>}
        </div>
        {headerComponent && headerComponent}
      </div>
      {children && <div className="boxContent">{children}</div>}
      {footer && <div className="boxFooter">{footer}</div>}
    </div>
  );
}
