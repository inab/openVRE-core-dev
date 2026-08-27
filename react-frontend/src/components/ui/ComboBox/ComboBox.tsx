import { ChevronDown, X } from 'lucide-react';
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

function filterItems(
  items: readonly ComboBoxOption[],
  inputValue: string,
): ComboBoxOption[] {
  const query = inputValue.trim().toLocaleLowerCase();
  if (!query) {
    return [...items];
  }

  return items.filter((item) => item.label.toLocaleLowerCase().includes(query));
}

function getSelectedLabel(
  items: readonly ComboBoxOption[],
  value: string | null,
): string {
  if (!value) {
    return '';
  }

  return items.find((item) => item.id === value)?.label ?? '';
}

export const ComboBox = ({
  'aria-label': ariaLabel,
  allowsClearing = false,
  allowsFiltering = true,
  icon: Icon,
  iconClassName,
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

  // Non-filterable selects always show the selected label (e.g. page size).
  const displayedInputValue = allowsFiltering ? inputValue : selectedLabel;

  const handleInputChange = (nextInputValue: string) => {
    if (!allowsFiltering) {
      return;
    }

    setInputValue(nextInputValue);

    if (allowsClearing && !nextInputValue.trim() && value != null) {
      onChange?.(null);
    }
  };

  const handleClear = () => {
    if (!allowsClearing) {
      return;
    }
    setInputValue('');
    onChange?.(null);
  };

  const menuItems = useMemo(
    () => (allowsFiltering ? filterItems(items, displayedInputValue) : items),
    [allowsFiltering, items, displayedInputValue],
  );

  const comboBox = (
    <AriaComboBox<ComboBoxOption>
      className="comboBox"
      items={menuItems}
      value={value}
      inputValue={displayedInputValue}
      menuTrigger="focus"
      onInputChange={handleInputChange}
      onChange={(key) => {
        // Required selects (no clearing) ignore empty selection events.
        if (key == null && !allowsClearing) {
          return;
        }
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
          readOnly={!allowsFiltering}
        />
        {allowsClearing && value != null ? (
          <Button
            slot={null}
            className="comboBoxClear"
            aria-label="Clear selection"
            onPress={handleClear}
          >
            <X
              className="comboBoxClearIcon"
              aria-hidden
            />
          </Button>
        ) : null}
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
        <Icon className={iconClassName} />
      </span>
      <div className="comboBoxControl">{comboBox}</div>
    </div>
  );
};
