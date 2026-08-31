import { useState } from 'react';
import {
  flexRender,
  useTable,
  type ExpandedState,
  type OnChangeFn,
  type RowSelectionState,
  type SortingState,
} from '@tanstack/react-table';

import type { WorkspacePageSize } from '../../../lib/workspace/pagination';
import type { FileItem } from '../../../lib/workspace/FileItem';
import { formatShowingEntries } from './formatShowingEntries';
import {
  getWorkspaceTableColClass,
  workspaceTableColumns,
  workspaceTableFeatures,
} from './workspaceTableColumns';
import { WorkspaceTablePagination } from './WorkspaceTablePagination';

import './WorkspaceTable.css';

const EMPTY_DATA: FileItem[] = [];

export interface WorkspaceTableProps {
  data: FileItem[];
  offset: number;
  pageCount: number;
  total: number;
  pageIndex: number;
  totalPages: number;
  pageSize: WorkspacePageSize;
  rowSelection: RowSelectionState;
  onRowSelectionChange: OnChangeFn<RowSelectionState>;
  onPageChange: (pageIndex: number) => void;
  onPageSizeChange: (pageSize: WorkspacePageSize) => void;
}

export const WorkspaceTable = ({
  data,
  offset,
  pageCount,
  total,
  pageIndex,
  totalPages,
  pageSize,
  rowSelection,
  onRowSelectionChange,
  onPageChange,
  onPageSizeChange,
}: WorkspaceTableProps) => {
  const [sorting, setSorting] = useState<SortingState>([]);
  const [expanded, setExpanded] = useState<ExpandedState>(true);
  const [prevData, setPrevData] = useState(data);

  // Page / search changes replace root rows; keep folders expanded by default.
  if (data !== prevData) {
    setPrevData(data);
    setExpanded(true);
  }

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
    onRowSelectionChange,
    getSubRows: (row) => row.children,
    getRowId: (row) => row.fileId,
    enableRowSelection: true,
    autoResetExpanded: false,
    sortDescFirst: false,
  });

  const rows = table.getRowModel().rows;

  return (
    <div className="workspaceTableRoot">
      <div className="workspaceTableWrap">
        <table className="workspaceTable">
          <colgroup>
            {table.getAllLeafColumns().map((column) => (
              <col
                key={column.id}
                className={getWorkspaceTableColClass(column.id)}
              />
            ))}
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
        <WorkspaceTablePagination
          pageIndex={pageIndex}
          totalPages={totalPages}
          pageSize={pageSize}
          onPageChange={onPageChange}
          onPageSizeChange={onPageSizeChange}
        />
      </div>
    </div>
  );
};
