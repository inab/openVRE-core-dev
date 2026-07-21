import css from './WorkspaceFileTable.css?inline'
import { injectCss } from '../../../lib/inject-css'
import { Box } from '../../ui/Box/Box'

injectCss('workspace-file-table-styles', css)

export function WorkspaceFileTable() {
  return (
    <Box
      title="Select File(s)"
      subtitle="Please select the file or files you want to use"
    >
      Hello from React — workspace file table island
    </Box>
  )
}
