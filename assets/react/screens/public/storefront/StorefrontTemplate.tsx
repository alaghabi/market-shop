import type { ComponentProps } from 'react';
import { NoirAtelierTemplate } from './NoirAtelierTemplate';
import { StorefrontTheme } from './StorefrontTheme';

type StorefrontTemplateProps = ComponentProps<typeof StorefrontTheme>;

export function StorefrontTemplate(props: StorefrontTemplateProps) {
  if (props.boutique.theme === 'noir-atelier') {
    return <NoirAtelierTemplate {...props} />;
  }

  return <StorefrontTheme {...props} />;
}
