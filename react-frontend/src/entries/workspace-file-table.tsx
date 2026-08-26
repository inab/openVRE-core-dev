import { WorkspaceFileTable } from '../components/workspace/WorkspaceFileTable/WorkspaceFileTable';
import { mountIsland } from '../lib/mount-island';

mountIsland('workspace-file-table-root', () => <WorkspaceFileTable />);
