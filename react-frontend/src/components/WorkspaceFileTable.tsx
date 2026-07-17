import css from './WorkspaceFileTable.css?inline'
import { injectCss } from '../lib/inject-css'

injectCss('workspace-file-table-styles', css)

export function WorkspaceFileTable() {
  return (
    <p className="workspace-file-table-banner">
      Hello from React — workspace file table island
    </p>
  )
}
