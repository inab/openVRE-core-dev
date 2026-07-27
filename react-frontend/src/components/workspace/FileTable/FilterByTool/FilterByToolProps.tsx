export interface ToolOption {
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
