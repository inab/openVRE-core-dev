import css from './Box.css?inline'
import { injectCss } from '../../../lib/inject-css'
import { BoxProps } from './BoxProps'

injectCss('box-styles', css)

export function Box({ 
  title,
  subtitle,
  children,
  footer,
}: BoxProps) {
  return (
    <div className="boxContainer">
      <div className="boxHeader">
        {title && <h3 className="boxTitle">{title}</h3>}
        {subtitle && <p className="boxSubtitle">{subtitle}</p>}
      </div>
      <div className="boxContent"> 
        {children}
      </div>
      <div className="boxFooter">
        {footer}
      </div>
    </div>
  )
}
