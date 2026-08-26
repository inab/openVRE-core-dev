import { Wrench } from 'lucide-react';

import { ComboBox } from '../../../ui/ComboBox/ComboBox';

import './FilterByTool.css';

interface ToolOption {
  id: string;
  name: string;
  dataTypes: string[];
}

export interface FilterByToolProps {
  tools: ToolOption[];
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
