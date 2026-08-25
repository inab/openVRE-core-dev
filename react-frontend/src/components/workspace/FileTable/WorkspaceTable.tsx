import { useState } from 'react';
import {
  flexRender,
  useTable,
  type ExpandedState,
  type RowSelectionState,
  type SortingState,
} from '@tanstack/react-table';

import { formatShowingEntries } from './formatters';
import type { FileItem } from './types/FileItem';
import {
  workspaceTableColumns,
  workspaceTableFeatures,
} from './workspaceTableColumns';

import './WorkspaceTable.css';

const EMPTY_DATA: FileItem[] = [];

export interface WorkspaceTableProps {
  data: FileItem[];
  offset: number;
  pageCount: number;
  total: number;
}

export const WorkspaceTable = ({
  data,
  offset,
  pageCount,
  total,
}: WorkspaceTableProps) => {
  const [sorting, setSorting] = useState<SortingState>([]);
  const [expanded, setExpanded] = useState<ExpandedState>(true);
  const [rowSelection, setRowSelection] = useState<RowSelectionState>({});

  const tableData = data.length > 0 ? data : EMPTY_DATA;

  const table = useTable({
    features: workspaceTableFeatures,
    data: tableData,
    columns: workspaceTableColumns,
    state: {
      sorting,
      expanded,
      rowSelection,
    },
    onSortingChange: setSorting,
    onExpandedChange: setExpanded,
    onRowSelectionChange: setRowSelection,
    getSubRows: (row) => row.children,
    getRowId: (row) => row.fileId,
    enableRowSelection: true,
    sortDescFirst: false,
  });

  const rows = table.getRowModel().rows;

  return (
    <div className="workspaceTableRoot">
      <div className="workspaceTableWrap">
        <table className="workspaceTable">
          <colgroup>
            <col className="workspaceTableColSelect" />
            <col className="workspaceTableColFile" />
            <col />
            <col />
            <col />
            <col />
            <col />
            <col className="workspaceTableColActions" />
          </colgroup>
          <thead>
            {table.getHeaderGroups().map((headerGroup) => (
              <tr key={headerGroup.id}>
                {headerGroup.headers.map((header) => {
                  const canSort = header.column.getCanSort();
                  return (
                    <th
                      key={header.id}
                      className={
                        canSort
                          ? 'workspaceTableTh workspaceTableThSortable'
                          : 'workspaceTableTh'
                      }
                      onClick={
                        canSort
                          ? header.column.getToggleSortingHandler()
                          : undefined
                      }
                      aria-sort={
                        header.column.getIsSorted() === 'asc'
                          ? 'ascending'
                          : header.column.getIsSorted() === 'desc'
                            ? 'descending'
                            : undefined
                      }
                    >
                      {header.isPlaceholder
                        ? null
                        : flexRender(
                            header.column.columnDef.header,
                            header.getContext(),
                          )}
                      {header.column.getIsSorted() === 'asc'
                        ? ' ↑'
                        : header.column.getIsSorted() === 'desc'
                          ? ' ↓'
                          : null}
                    </th>
                  );
                })}
              </tr>
            ))}
          </thead>
          <tbody>
            {rows.map((row) => (
              <tr
                key={row.id}
                className={
                  row.getIsSelected()
                    ? 'workspaceTableRow workspaceTableRowSelected'
                    : 'workspaceTableRow'
                }
                data-kind={row.original.kind}
              >
                {row.getAllCells().map((cell) => (
                  <td
                    key={cell.id}
                    className="workspaceTableTd"
                  >
                    {flexRender(cell.column.columnDef.cell, cell.getContext())}
                  </td>
                ))}
              </tr>
            ))}
          </tbody>
        </table>
      </div>
      <div className="workspaceTableFooter">
        <p className="workspaceTableInfo">
          {formatShowingEntries(offset, pageCount, total)}
        </p>
      </div>
    </div>
  );
};
