import type { LucideIcon } from 'lucide-react';

export interface ComboBoxOption {
  id: string;
  label: string;
}

export interface ComboBoxProps {
  'aria-label'?: string;
  allowsFiltering?: boolean;
  icon?: LucideIcon;
  iconClassName?: string;
  items: readonly ComboBoxOption[];
  isDisabled?: boolean;
  label?: string;
  onChange?: (id: string | null) => void;
  placeholder?: string;
  value?: string | null;
}
