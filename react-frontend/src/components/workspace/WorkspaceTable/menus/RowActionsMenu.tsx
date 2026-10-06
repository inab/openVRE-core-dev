import { ChevronDown, Cog } from 'lucide-react';
import {
  Button,
  Menu,
  MenuItem,
  MenuTrigger,
  Popover,
} from 'react-aria-components';

import type { FileItem } from '../../../../lib/workspace/FileItem';
import { resolveFileItemActions } from '../../../../lib/workspace/fileItemKind';
import { FILE_ITEM_ACTION_ICONS } from './fileActionIcons';
import { FILE_ITEM_ACTION_LABELS, stubFileAction } from './fileActionLabels';

import './RowActionsMenu.css';

export interface RowActionsMenuProps {
  item: FileItem;
}

export const RowActionsMenu = ({ item }: RowActionsMenuProps) => {
  const actions = resolveFileItemActions(item);
  if (actions.length === 0) {
    return null;
  }

  return (
    <MenuTrigger>
      <Button
        className="rowActionsMenuTrigger"
        aria-label={`Actions for ${item.filename}`}
      >
        <Cog
          aria-hidden
          className="rowActionsMenuIcon"
          size={12}
        />
        <ChevronDown
          aria-hidden
          className="rowActionsMenuChevron"
          size={10}
        />
      </Button>
      <Popover
        className="rowActionsMenuPopover"
        placement="bottom end"
      >
        <Menu
          className="rowActionsMenu"
          onAction={(key) => {
            stubFileAction(
              String(key) as (typeof actions)[number],
              item.fileId,
            );
          }}
        >
          {actions.map((action) => {
            const Icon = FILE_ITEM_ACTION_ICONS[action];
            return (
              <MenuItem
                key={action}
                id={action}
                className="rowActionsMenuItem"
                textValue={FILE_ITEM_ACTION_LABELS[action]}
              >
                <Icon
                  aria-hidden
                  className="rowActionsMenuItemIcon"
                  size={14}
                />
                {FILE_ITEM_ACTION_LABELS[action]}
              </MenuItem>
            );
          })}
        </Menu>
      </Popover>
    </MenuTrigger>
  );
};
