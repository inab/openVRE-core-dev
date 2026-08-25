import { useMemo, useState } from 'react';
import { reloadCurrentPage } from '../../../lib/navigation';

import { Box } from '../../ui/Box/Box';
import { Button } from '../../ui/Button/Button';
import { SearchField } from '../../ui/SearchField/SearchField';
import { useFilesQuery } from '../../../hooks/useFilesQuery';
import { useToolsQuery } from '../../../hooks/useToolsQuery';
import { adaptFilesPage } from './adapter/adaptFilesPage';
import { FilterByTool } from './FilterByTool/FilterByTool';
import { WorkspaceTable } from './WorkspaceTable';

import './WorkspaceFileTable.css';

const getToolParam = (): string | null => {
  const tool = new URLSearchParams(window.location.search).get('tool');
  return tool && tool.length > 0 ? tool : null;
};

const setToolParam = (toolId: string | null): void => {
  const url = new URL(window.location.href);
  if (toolId) {
    url.searchParams.set('tool', toolId);
  } else {
    url.searchParams.delete('tool');
  }
  window.history.replaceState(null, '', url);
};

export const WorkspaceFileTable = () => {
  const [searchQuery, setSearchQuery] = useState('');
  const [selectedToolId, setSelectedToolId] = useState<string | null>(
    getToolParam,
  );
  const toolsQuery = useToolsQuery();
  const filesQuery = useFilesQuery();
  const tools = toolsQuery.data?.tools ?? [];

  const tree = useMemo(
    () => adaptFilesPage(filesQuery.data?.files ?? []),
    [filesQuery.data?.files],
  );

  const handleToolChange = (toolId: string | null) => {
    setSelectedToolId(toolId);
    setToolParam(toolId);
  };

  return (
    <Box
      title="Select File(s)"
      subtitle="Please select the file or files you want to use"
      headerComponent={
        <Button
          label="Reload Workspace"
          onClick={reloadCurrentPage}
        />
      }
    >
      <div className="workspaceFileTableToolbar">
        <FilterByTool
          tools={tools}
          value={selectedToolId}
          onChange={handleToolChange}
        />
        <SearchField
          value={searchQuery}
          onChange={setSearchQuery}
          placeholder="Search files"
          aria-label="Search files"
        />
      </div>
      {toolsQuery.isError ? (
        <p className="workspaceFileTableStatus">Could not load tools.</p>
      ) : null}
      {filesQuery.isPending ? (
        <p className="workspaceFileTableStatus">Loading files…</p>
      ) : null}
      {filesQuery.isError ? (
        <p className="workspaceFileTableStatus">Could not load files.</p>
      ) : null}
      {filesQuery.isSuccess ? (
        <WorkspaceTable
          data={tree}
          offset={filesQuery.data.offset}
          pageCount={filesQuery.data.files.length}
          total={filesQuery.data.total}
        />
      ) : null}
    </Box>
  );
};
