import { Search, X } from 'lucide-react';
import {
  Button,
  Input,
  SearchField as AriaSearchField,
} from 'react-aria-components';

import { SearchFieldProps } from './SearchFieldProps';

import './SearchField.css';

export const SearchField = ({
  'aria-label': ariaLabel,
  icon: Icon = Search,
  iconClassName = 'searchFieldIcon',
  isDisabled,
  onChange,
  placeholder = 'Search files',
  showIcon = true,
  value = '',
}: SearchFieldProps) => {
  const searchField = (
    <AriaSearchField
      aria-label={ariaLabel}
      className="searchField"
      isDisabled={isDisabled}
      value={value}
      onChange={onChange}
    >
      <div className="searchFieldField">
        <Input
          className="searchFieldInput"
          placeholder={placeholder}
        />
        {value.length > 0 ? (
          <Button
            className="searchFieldClear"
            aria-label="Clear search"
          >
            <X
              className="searchFieldClearIcon"
              aria-hidden
            />
          </Button>
        ) : null}
      </div>
    </AriaSearchField>
  );

  if (!showIcon) {
    return searchField;
  }

  return (
    <div className="searchFieldWithIcon">
      <span
        className="searchFieldAddon"
        aria-hidden
      >
        <Icon className={iconClassName} />
      </span>
      <div className="searchFieldControl">{searchField}</div>
    </div>
  );
};
