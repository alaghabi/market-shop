import { FontAwesomeIcon } from '@fortawesome/react-fontawesome';
import {
  faFacebook,
  faInstagram,
  faLinkedin,
  faTiktok,
  faWhatsapp,
  faXTwitter,
  faYoutube,
} from '@fortawesome/free-brands-svg-icons';
import type { IconDefinition } from '@fortawesome/fontawesome-svg-core';

export type SocialLinksValue = Record<string, string | null | undefined>;

type SocialNetwork = {
  key: string;
  label: string;
  icon: IconDefinition;
};

const NETWORKS: SocialNetwork[] = [
  { key: 'facebook', label: 'Facebook', icon: faFacebook },
  { key: 'instagram', label: 'Instagram', icon: faInstagram },
  { key: 'tiktok', label: 'TikTok', icon: faTiktok },
  { key: 'youtube', label: 'YouTube', icon: faYoutube },
  { key: 'linkedin', label: 'LinkedIn', icon: faLinkedin },
  { key: 'x_twitter', label: 'X / Twitter', icon: faXTwitter },
  { key: 'whatsapp', label: 'WhatsApp', icon: faWhatsapp },
];

function resolveSocialUrl(network: string, value: string | null | undefined): string | null {
  const normalized = value?.trim() ?? '';
  if ('' === normalized) return null;

  if ('whatsapp' === network && !/^https:\/\//i.test(normalized)) {
    const phone = normalized.replace(/\D/g, '').replace(/^00/, '');
    return phone ? `https://wa.me/${phone}` : null;
  }

  return /^https:\/\//i.test(normalized) ? normalized : null;
}

export function SocialLinks({
  links,
  className = 'flex flex-wrap items-center gap-3',
  itemClassName = 'inline-flex h-9 w-9 items-center justify-center rounded-full transition hover:-translate-y-0.5',
}: {
  links?: SocialLinksValue;
  className?: string;
  itemClassName?: string;
}) {
  const resolved = NETWORKS
    .map((network) => ({ ...network, href: resolveSocialUrl(network.key, links?.[network.key]) }))
    .filter((network): network is SocialNetwork & { href: string } => Boolean(network.href));

  if (resolved.length === 0) return null;

  return (
    <nav className={className} aria-label="Réseaux sociaux">
      {resolved.map((network) => (
        <a
          key={network.key}
          href={network.href}
          target="_blank"
          rel="noopener noreferrer"
          aria-label={network.label}
          title={network.label}
          className={itemClassName}
        >
          <FontAwesomeIcon icon={network.icon} aria-hidden="true" />
        </a>
      ))}
    </nav>
  );
}
