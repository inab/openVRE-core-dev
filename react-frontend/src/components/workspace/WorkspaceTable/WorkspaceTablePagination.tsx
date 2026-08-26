import { ComboBox } from '../../ui/ComboBox/ComboBox';
import {
  pageSelectorItems,
  parseWorkspacePageSize,
  WORKSPACE_PAGE_SIZE_ITEMS,
  type WorkspacePageSize,
} from '../../../lib/workspace/pagination';

import './WorkspaceTablePagination.css';

export interface WorkspaceTablePaginationProps {
  pageIndex: number;
  totalPages: number;
  pageSize: WorkspacePageSize;
  onPageChange: (pageIndex: number) => void;
  onPageSizeChange: (pageSize: WorkspacePageSize) => void;
}

export const WorkspaceTablePagination = ({
  pageIndex,
  totalPages,
  pageSize,
  onPageChange,
  onPageSizeChange,
}: WorkspaceTablePaginationProps) => {
  const items = pageSelectorItems(pageIndex, totalPages);
  const showPageButtons = totalPages > 1;

  const handlePageSizeChange = (id: string | null) => {
    const next = parseWorkspacePageSize(id);
    if (next != null) {
      onPageSizeChange(next);
    }
  };

  const handlePreviousPage = () => {
    onPageChange(pageIndex - 1);
  };

  const handleNextPage = () => {
    onPageChange(pageIndex + 1);
  };

  return (
    <div className="workspaceTablePagination">
      <div className="workspaceTablePageSize">
        <ComboBox
          allowsFiltering={false}
          items={WORKSPACE_PAGE_SIZE_ITEMS}
          value={String(pageSize)}
          onChange={handlePageSizeChange}
          placeholder="Per page"
          aria-label="Items per page"
        />
      </div>
      {showPageButtons ? (
        <nav
          className="workspaceTablePaginationNav"
          aria-label="Workspace table pages"
        >
          <button
            type="button"
            className="workspaceTablePaginationBtn"
            disabled={pageIndex <= 0}
            onClick={handlePreviousPage}
            aria-label="Previous page"
          >
            &lt;
          </button>
          <ul className="workspaceTablePaginationList">
            {items.map((item, index) =>
              item === 'ellipsis' ? (
                <li
                  key={`ellipsis-${index}`}
                  className="workspaceTablePaginationEllipsis"
                  aria-hidden="true"
                >
                  …
                </li>
              ) : (
                <li key={item}>
                  <button
                    type="button"
                    className={
                      item === pageIndex
                        ? 'workspaceTablePaginationBtn workspaceTablePaginationBtnActive'
                        : 'workspaceTablePaginationBtn'
                    }
                    aria-label={`Page ${item + 1}`}
                    aria-current={item === pageIndex ? 'page' : undefined}
                    onClick={() => onPageChange(item)}
                  >
                    {item + 1}
                  </button>
                </li>
              ),
            )}
          </ul>
          <button
            type="button"
            className="workspaceTablePaginationBtn"
            disabled={pageIndex >= totalPages - 1}
            onClick={handleNextPage}
            aria-label="Next page"
          >
            &gt;
          </button>
        </nav>
      ) : null}
    </div>
  );
};
