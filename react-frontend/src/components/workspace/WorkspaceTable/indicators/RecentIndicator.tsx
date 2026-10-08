import './RecentIndicator.css';

export interface RecentIndicatorProps {
  label: string;
}

export const RecentIndicator = ({ label }: RecentIndicatorProps) => (
  <span
    className="recentFileIndicator"
    title="File recently added"
  >
    {label}
  </span>
);
