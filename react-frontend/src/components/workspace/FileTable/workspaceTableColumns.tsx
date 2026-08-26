import {
  createColumnHelper,
  createExpandedRowModel,
  createSortedRowModel,
  rowExpandingFeature,
  rowSelectionFeature,
  rowSortingFeature,
  sortFn_alphanumeric,
  sortFn_basic,
  sortFn_datetime,
  tableFeatures,
} from '@tanstack/react-table';

import type { FileItem } from './types/FileItem';
import { formatFileDate, formatFileSize } from './formatters';
import { RowActionsMenu } from './menus/RowActionsMenu';
import { WorkspaceRowName } from './rows/WorkspaceRow';

export const workspaceTableFeatures = tableFeatures({
  rowExpandingFeature,
  rowSortingFeature,
  rowSelectionFeature,
  expandedRowModel: createExpandedRowModel(),
  sortedRowModel: createSortedRowModel(),
  sortFns: {
    alphanumeric: sortFn_alphanumeric,
    datetime: sortFn_datetime,
    basic: sortFn_basic,
  },
});

const columnHelper = createColumnHelper<
  typeof workspaceTableFeatures,
  FileItem
>();

export const workspaceTableColumns = columnHelper.columns([
  columnHelper.display({
    id: 'select',
    header: ({ table }) => (
      <input
        type="checkbox"
        aria-label="Select all files"
        checked={table.getIsAllPageRowsSelected()}
        ref={(input) => {
          if (input) {
            input.indeterminate = table.getIsSomePageRowsSelected();
          }
        }}
        onChange={table.getToggleAllPageRowsSelectedHandler()}
      />
    ),
    cell: ({ row }) => (
      <input
        type="checkbox"
        aria-label={`Select ${row.original.filename}`}
        checked={row.getIsSelected()}
        disabled={!row.getCanSelect()}
        onChange={row.getToggleSelectedHandler()}
      />
    ),
    enableSorting: false,
  }),
  columnHelper.accessor('filename', {
    header: 'File',
    cell: ({ row }) => (
      <WorkspaceRowName
        item={row.original}
        depth={row.depth}
        canExpand={row.getCanExpand()}
        isExpanded={row.getIsExpanded()}
        onToggleExpand={row.getToggleExpandedHandler()}
      />
    ),
    sortFn: 'alphanumeric',
  }),
  columnHelper.accessor('format', {
    header: 'File type',
    cell: (info) => info.getValue() || '',
  }),
  columnHelper.accessor('dataType', {
    header: 'Data type',
    cell: (info) => info.getValue() || '',
  }),
  columnHelper.accessor('date', {
    header: 'Date',
    cell: (info) => formatFileDate(info.getValue()),
    sortFn: 'datetime',
  }),
  columnHelper.accessor('size', {
    header: 'Size',
    cell: (info) => formatFileSize(info.getValue()),
    sortFn: 'basic',
  }),
  columnHelper.display({
    id: 'actions',
    header: 'Actions',
    cell: ({ row }) => <RowActionsMenu item={row.original} />,
    enableSorting: false,
  }),
]);
