import type { LucideIcon } from 'lucide-react';

export interface ComboBoxOption {
  id: string;
  label: string;
}

export interface ComboBoxProps {
  'aria-label'?: string;
  icon?: LucideIcon;
  items: ComboBoxOption[];
  isDisabled?: boolean;
  label?: string;
  onChange?: (id: string | null) => void;
  placeholder?: string;
  value?: string | null;
}
