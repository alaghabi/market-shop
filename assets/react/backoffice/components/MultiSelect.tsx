export type MultiSelectOption = {
  value: string;
  label: string;
};

export function MultiSelect({
  options,
  value,
  onChange,
  disabled = false,
  ariaLabel,
}: {
  options: MultiSelectOption[];
  value: string[];
  onChange: (value: string[]) => void;
  disabled?: boolean;
  ariaLabel: string;
}) {
  function toggle(optionValue: string): void {
    if (disabled) return;
    onChange(value.includes(optionValue) ? value.filter((item) => item !== optionValue) : [...value, optionValue]);
  }

  return (
    <div className="bo-filter-multiselect" role="group" aria-label={ariaLabel}>
      <div className="bo-filter-multiselect-options">
        {options.map((option) => {
          const selected = value.includes(option.value);

          return (
            <label
              key={option.value}
              className={`bo-filter-multiselect-option${selected ? ' is-selected' : ''}`}
            >
              <input
                type="checkbox"
                checked={selected}
                disabled={disabled}
                onChange={() => toggle(option.value)}
              />
              <span>{option.label}</span>
            </label>
          );
        })}
      </div>
      <span className="bo-filter-multiselect-summary">
        {value.length === 0 ? 'Aucune sélection' : `${value.length} sélectionnée${value.length > 1 ? 's' : ''}`}
      </span>
    </div>
  );
}
