import './WorkspaceFileTable.css';
import { Box } from '../../ui/Box/Box';
import { Button } from '../../ui/Button/Button';

export function WorkspaceFileTable() {
  return (
    <Box
      title="Select File(s)"
      subtitle="Please select the file or files you want to use"
      headerComponent={
        <Button
          label="Reload Workspace"
          onClick={() => {}}
        />
      }
    >
      Hello from React — workspace file table island
    </Box>
  );
}
