import type { ComponentProps } from 'react';
import { StorefrontTheme } from './StorefrontTheme';

export type NoirAtelierTemplateProps = ComponentProps<typeof StorefrontTheme>;

/**
 * Editorial storefront shell. The business data and actions stay shared with
 * the default renderer while this template owns its visual language.
 */
export function NoirAtelierTemplate(props: NoirAtelierTemplateProps) {
  return (
    <div className="sf-template sf-template--noir-atelier">
      <StorefrontTheme {...props} />
    </div>
  );
}
