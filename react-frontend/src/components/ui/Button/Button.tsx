import './Button.css';
import { ButtonProps } from './ButtonProps';

export function Button({ label, onClick }: ButtonProps) {
  return (
    <button
      className="button"
      onClick={onClick}
    >
      <span className="buttonLabel">{label}</span>
    </button>
  );
}
