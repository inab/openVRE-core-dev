export interface BoxProps {
  children: React.ReactNode;
  footer?: React.ReactNode;
  isCollapsable?: boolean;
  headerComponent?: React.ReactNode;
  subtitle?: string;
  title?: string;
}
