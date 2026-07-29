import { useEffect, useState } from 'react';
import { SocialLinks, type SocialLinksValue } from './SocialLinks';

type PublicAppConfig = {
  socialLinks?: SocialLinksValue;
};

export function PlatformSocialLinks({
  className,
  itemClassName,
}: {
  className?: string;
  itemClassName?: string;
}) {
  const [links, setLinks] = useState<SocialLinksValue>({});

  useEffect(() => {
    const controller = new AbortController();

    fetch('/api/public/app-config', { signal: controller.signal })
      .then((response) => response.ok ? response.json() as Promise<PublicAppConfig> : Promise.reject(new Error('Platform config unavailable')))
      .then((config) => setLinks(config.socialLinks ?? {}))
      .catch(() => {
        if (!controller.signal.aborted) setLinks({});
      });

    return () => controller.abort();
  }, []);

  return <SocialLinks links={links} className={className} itemClassName={itemClassName} />;
}
