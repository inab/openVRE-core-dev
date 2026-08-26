import { useMemo, useState } from 'react';
import type { RowSelectionState } from '@tanstack/react-table';
import { reloadCurrentPage } from '../../../lib/navigation';

import { Box } from '../../ui/Box/Box';
import { Button } from '../../ui/Button/Button';
import { SearchField } from '../../ui/SearchField/SearchField';
import { useFilesQuery } from '../../../hooks/useFilesQuery';
import { useToolsQuery } from '../../../hooks/useToolsQuery';
import { adaptFilesPage } from './adapter/adaptFilesPage';
import { filterFilesBySearch } from './adapter/filterWorkspaceFiles';
import { orderWorkspaceFiles } from './adapter/orderWorkspaceFiles';
import { FilterByTool } from './FilterByTool/FilterByTool';
import {
  clampPageIndex,
  pageRootFolders,
  totalPagesForRoots,
  WORKSPACE_ROOT_PAGE_SIZE,
  type WorkspacePageSize,
} from './pagination';
import { WorkspaceTable } from './WorkspaceTable';

import './WorkspaceFileTable.css';

function getToolParam(): string | null {
  const tool = new URLSearchParams(window.location.search).get('tool');
  return tool && tool.length > 0 ? tool : null;
}

function setToolParam(toolId: string | null): void {
  const url = new URL(window.location.href);
  if (toolId) {
    url.searchParams.set('tool', toolId);
  } else {
    url.searchParams.delete('tool');
  }
  window.history.replaceState(null, '', url);
}

export const WorkspaceFileTable = () => {
  const [searchQuery, setSearchQuery] = useState('');
  const [selectedToolId, setSelectedToolId] = useState<string | null>(
    getToolParam,
  );
  const [pageIndex, setPageIndex] = useState(0);
  const [pageSize, setPageSize] = useState<WorkspacePageSize>(
    WORKSPACE_ROOT_PAGE_SIZE,
  );
  const [rowSelection, setRowSelection] = useState<RowSelectionState>({});
  const toolsQuery = useToolsQuery();
  const filesQuery = useFilesQuery();
  const tools = toolsQuery.data?.tools ?? [];
  const allFiles = filesQuery.data?.files ?? [];

  const filteredFiles = useMemo(
    () => filterFilesBySearch(allFiles, searchQuery),
    [allFiles, searchQuery],
  );

  const orderedFiles = useMemo(
    () => orderWorkspaceFiles(filteredFiles),
    [filteredFiles],
  );

  const totalPages = totalPagesForRoots(orderedFiles.length, pageSize);
  const safePageIndex = clampPageIndex(pageIndex, totalPages);

  const { page: pageFiles, offset, total, pageCount } = useMemo(
    () => pageRootFolders(orderedFiles, safePageIndex, pageSize),
    [orderedFiles, safePageIndex, pageSize],
  );

  const page = useMemo(() => adaptFilesPage(pageFiles), [pageFiles]);

  const handleSearchChange = (query: string) => {
    setSearchQuery(query);
    setPageIndex(0);
    setRowSelection({});
  };

  const handlePageSizeChange = (nextPageSize: WorkspacePageSize) => {
    setPageSize(nextPageSize);
    setPageIndex(0);
  };

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
          onChange={handleSearchChange}
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
          data={page}
          offset={offset}
          pageCount={pageCount}
          total={total}
          pageIndex={safePageIndex}
          totalPages={totalPages}
          pageSize={pageSize}
          rowSelection={rowSelection}
          onRowSelectionChange={setRowSelection}
          onPageChange={setPageIndex}
          onPageSizeChange={handlePageSizeChange}
        />
      ) : null}
    </Box>
  );
};
