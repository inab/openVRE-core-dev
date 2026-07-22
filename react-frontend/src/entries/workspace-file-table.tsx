import { WorkspaceFileTable } from '../components/workspace/FileTable/WorkspaceFileTable';
import { mountIsland } from '../lib/mount-island';

mountIsland('workspace-file-table-root', () => <WorkspaceFileTable />);
