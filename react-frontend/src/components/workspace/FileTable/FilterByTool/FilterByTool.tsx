import { Wrench } from 'lucide-react';

import { ComboBox } from '../../../ui/ComboBox/ComboBox';
import { FilterByToolProps } from './FilterByToolProps';

import './FilterByTool.css';

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
        items={items}
        value={value}
        onChange={onChange}
        placeholder={placeholder}
        aria-label={placeholder}
      />
    </div>
  );
}
