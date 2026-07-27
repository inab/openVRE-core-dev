import { ChevronDown } from 'lucide-react';
import { useMemo, useState } from 'react';
import {
  Button,
  ComboBox as AriaComboBox,
  Input,
  ListBox,
  ListBoxItem,
  Label,
  Popover,
} from 'react-aria-components';

import { ComboBoxOption, ComboBoxProps } from './ComboBoxProps';

import './ComboBox.css';

const filterItems = (
  items: ComboBoxOption[],
  inputValue: string,
): ComboBoxOption[] => {
  const query = inputValue.trim().toLocaleLowerCase();
  if (!query) {
    return items;
  }

  return items.filter((item) => item.label.toLocaleLowerCase().includes(query));
};

const getSelectedLabel = (
  items: ComboBoxOption[],
  value: string | null,
): string => {
  if (!value) {
    return '';
  }

  return items.find((item) => item.id === value)?.label ?? '';
};

export const ComboBox = ({
  'aria-label': ariaLabel,
  icon: Icon,
  items,
  isDisabled,
  label,
  onChange,
  placeholder,
  value = null,
}: ComboBoxProps) => {
  const selectedLabel = getSelectedLabel(items, value);
  const [inputValue, setInputValue] = useState(selectedLabel);
  const [prevValue, setPrevValue] = useState(value);

  if (value !== prevValue) {
    setPrevValue(value);
    setInputValue(selectedLabel);
  }

  const handleInputChange = (nextInputValue: string) => {
    setInputValue(nextInputValue);

    if (!nextInputValue.trim() && value != null) {
      onChange?.(null);
    }
  };

  const filteredItems = useMemo(
    () => filterItems(items, inputValue),
    [items, inputValue],
  );

  const comboBox = (
    <AriaComboBox<ComboBoxOption>
      className="comboBox"
      items={filteredItems}
      value={value}
      inputValue={inputValue}
      menuTrigger="focus"
      onInputChange={handleInputChange}
      onChange={(key) => {
        onChange?.(key == null ? null : String(key));
      }}
      isDisabled={isDisabled}
      aria-label={ariaLabel}
    >
      {label ? <Label className="comboBoxLabel">{label}</Label> : null}
      <div className="comboBoxField">
        <Input
          className="comboBoxInput"
          placeholder={placeholder}
        />
        <Button
          className="comboBoxTrigger"
          aria-label="Open options"
        >
          <ChevronDown
            className="comboBoxTriggerIcon"
            aria-hidden
          />
        </Button>
      </div>
      <Popover className="comboBoxPopover">
        <ListBox<ComboBoxOption> className="comboBoxList">
          {(item) => (
            <ListBoxItem
              id={item.id}
              textValue={item.label}
              className="comboBoxItem"
            >
              {item.label}
            </ListBoxItem>
          )}
        </ListBox>
      </Popover>
    </AriaComboBox>
  );

  if (!Icon) {
    return comboBox;
  }

  return (
    <div className="comboBoxWithIcon">
      <span
        className="comboBoxAddon"
        aria-hidden
      >
        <Icon className="comboBoxAddonIcon" />
      </span>
      <div className="comboBoxControl">{comboBox}</div>
    </div>
  );
};
