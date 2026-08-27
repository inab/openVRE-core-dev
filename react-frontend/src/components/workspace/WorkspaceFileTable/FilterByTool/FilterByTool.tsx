import { Wrench } from 'lucide-react';

import type { Tool } from '../../../../types/Tool';
import { ComboBox } from '../../../ui/ComboBox/ComboBox';

import './FilterByTool.css';

export interface FilterByToolProps {
  tools: Tool[];
  value?: string | null;
  onChange?: (toolId: string | null) => void;
  placeholder?: string;
}

export const FilterByTool = ({
  tools,
  value = null,
  onChange,
  placeholder = 'Filter files by tool',
}: FilterByToolProps) => {
  const items = tools.map((tool) => ({
    id: tool.id,
    label: tool.name,
  }));

  return (
    <div className="filterByTool">
      <ComboBox
        allowsClearing
        icon={Wrench}
        iconClassName="filterByToolIcon"
        items={items}
        value={value}
        onChange={onChange}
        placeholder={placeholder}
        aria-label={placeholder}
      />
    </div>
  );
};
