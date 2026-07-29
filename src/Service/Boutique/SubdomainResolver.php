<?php

namespace App\Service\Boutique;

use App\Entity\Boutique;
use App\Repository\BoutiqueRepository;
use Symfony\Component\HttpFoundation\Request;

final class SubdomainResolver
{
    /** @var list<string> */
    private const EXCLUDED_SUBDOMAINS = ['www', 'api', 'admin', 'mail', 'auth', 'staging', 'dev'];

    public function __construct(
        private BoutiqueRepository $boutiques,
        private string $rootDomain,
    ) {
    }

    public function resolveFromRequest(Request $request): ?Boutique
    {
        $host = $request->getHost();
        $slug = $this->extractSubdomain($host);

        if (null === $slug || '' === $slug) {
            return null;
        }

        return $this->boutiques->findBySlug($slug);
    }

    public function extractSubdomain(string $host): ?string
    {
        $host = strtolower(trim($host, '.'));

        // Strip root domain to isolate subdomain
        $rootDomain = strtolower(trim($this->rootDomain, '.'));
        if ('' === $rootDomain) {
            return null;
        }

        if ('' !== $rootDomain && $host === $rootDomain) {
            return null;
        }

        if (str_ends_with($host, '.'.$rootDomain)) {
            $host = substr($host, 0, -strlen('.'.$rootDomain));
        } elseif ($host !== $rootDomain) {
            return null;
        }

        // Extract the first segment as subdomain
        $parts = explode('.', $host);
        $subdomain = $parts[0] ?? '';

        if ('' === $subdomain || in_array($subdomain, self::EXCLUDED_SUBDOMAINS, true)) {
            return null;
        }

        return $subdomain;
    }
}
