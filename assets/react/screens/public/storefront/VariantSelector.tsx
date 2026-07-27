type VariantAttribute = { name: string; value: string };

export type VariantSelectorOption = {
  attributes: VariantAttribute[];
  quantity?: number;
};

const colorSwatches: Record<string, string> = {
  argent: '#cbd5e1',
  argenté: '#cbd5e1',
  beige: '#d6c7a1',
  blanc: '#ffffff',
  bleu: '#3b82f6',
  blue: '#3b82f6',
  bronze: '#a16207',
  cuivre: '#ea580c',
  doré: '#d4af37',
  gris: '#64748b',
  jaune: '#facc15',
  marron: '#92400e',
  noir: '#111827',
  orange: '#f97316',
  or: '#d4af37',
  rose: '#ec4899',
  rouge: '#ef4444',
  vert: '#22c55e',
  violet: '#8b5cf6',
};

function isColorAttribute(name: string): boolean {
  return /couleur|color/i.test(name);
}

function getColorSwatch(value: string): string {
  const normalized = value.trim().toLowerCase();
  if (/^#[0-9a-f]{3,8}$/i.test(normalized)) return normalized;

  return colorSwatches[normalized] ?? '#cbd5e1';
}

function variantOptionId(prefix: string, name: string, value: string): string {
  return `${prefix}-${name}-${value}`.toLowerCase().replace(/[^a-z0-9]+/g, '-');
}

export function VariantSelector({
  variants,
  selectedAttributes,
  onSelect,
  idPrefix,
  disabled = false,
}: {
  variants: VariantSelectorOption[];
  selectedAttributes: Record<string, string>;
  onSelect: (name: string, value: string) => void;
  idPrefix: string;
  disabled?: boolean;
}) {
  const groups = Array.from(new Set(variants.flatMap((variant) => variant.attributes.map((attribute) => attribute.name))))
    .map((name) => ({
      name,
      values: Array.from(new Set(variants.flatMap((variant) => variant.attributes.filter((attribute) => attribute.name === name).map((attribute) => attribute.value)))),
    }));

  if (groups.length === 0) return null;

  return (
    <div className="space-y-4">
      <p className="text-sm font-semibold">Variante</p>
      {groups.map((group) => (
        <fieldset key={group.name} className="block text-sm font-semibold" disabled={disabled}>
          <legend className="mb-3 block">{group.name}</legend>
          <div className="flex flex-wrap items-center gap-3">
            {group.values.map((value) => {
              const optionId = variantOptionId(idPrefix, group.name, value);
              const isSelected = selectedAttributes[group.name] === value;
              const isAvailable = variants.some((variant) => (
                (variant.quantity ?? 1) > 0
                && variant.attributes.some((attribute) => attribute.name === group.name && attribute.value === value)
                && Object.entries(selectedAttributes).every(([key, val]) =>
                  key === group.name || variant.attributes.some((a) => a.name === key && a.value === val)
                )
              ));

              return (
                <label key={value} htmlFor={optionId} className={`cursor-pointer ${isColorAttribute(group.name) ? 'flex flex-col items-center gap-1.5' : ''} ${!isAvailable ? 'cursor-not-allowed opacity-45' : ''}`}>
                  <input
                    id={optionId}
                    type="radio"
                    name={`${idPrefix}-variant-${group.name}`}
                    value={value}
                    checked={isSelected}
                    onChange={() => onSelect(group.name, value)}
                    disabled={disabled || !isAvailable}
                    className="sr-only"
                  />
                  {isColorAttribute(group.name) ? (
                    <span
                      className={`block h-10 w-10 rounded-full border-2 transition ${isSelected ? 'border-[color:var(--ds-primary)] ring-2 ring-[color:var(--ds-primary)] ring-offset-2' : 'border-[color:var(--ds-outline-variant)]'}`}
                      style={{ backgroundColor: getColorSwatch(value) }}
                      title={value}
                      aria-hidden="true"
                    />
                  ) : (
                    <span className={`inline-flex min-h-10 items-center rounded-xl border px-4 py-2 transition ${isSelected ? 'border-[color:var(--ds-primary)] bg-[color:var(--ds-primary-container)] text-[color:var(--ds-on-primary)]' : 'border-[color:var(--ds-outline-variant)] bg-white text-[color:var(--ds-on-surface)]'}`}>
                      {value}
                    </span>
                  )}
                  {isColorAttribute(group.name) && <span className="text-xs font-medium text-[color:var(--ds-on-surface-variant)]">{value}</span>}
                </label>
              );
            })}
          </div>
        </fieldset>
      ))}
    </div>
  );
}
