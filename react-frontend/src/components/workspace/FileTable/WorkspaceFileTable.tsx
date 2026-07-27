import { useState } from 'react';
import { reloadCurrentPage } from '../../../lib/navigation';

import { Box } from '../../ui/Box/Box';
import { Button } from '../../ui/Button/Button';
import { FilterByTool } from './FilterByTool/FilterByTool';
import toolsMock from './mocks/tools.json';

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
  const [selectedToolId, setSelectedToolId] = useState<string | null>(
    getToolParam,
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
      <FilterByTool
        tools={toolsMock.tools}
        value={selectedToolId}
        onChange={handleToolChange}
      />
    </Box>
  );
}
