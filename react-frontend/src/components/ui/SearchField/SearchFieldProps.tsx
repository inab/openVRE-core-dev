import type { LucideIcon } from 'lucide-react';

export interface SearchFieldProps {
  'aria-label'?: string;
  icon?: LucideIcon;
  iconClassName?: string;
  isDisabled?: boolean;
  onChange?: (value: string) => void;
  placeholder?: string;
  showIcon?: boolean;
  value?: string;
}
