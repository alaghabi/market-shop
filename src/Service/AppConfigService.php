<?php

namespace App\Service;

use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

final readonly class AppConfigService
{
    private string $configPath;

    public function __construct(
        string $projectDir,
        private CacheInterface $cache,
    ) {
        $this->configPath = $projectDir.'/var/data/app_config.json';
    }

    /** @return array<string, mixed> */
    public function get(): array
    {
        return $this->cache->get('platform.app_config', function (ItemInterface $item): array {
            $item->expiresAfter(21600);

            return $this->readFromDisk();
        });
    }

    /** @return array<string, mixed> */
    public function update(array $data): array
    {
        $config = $this->mergeConfig($this->get(), $data, $this->defaults());
        $config['platform_social_links'] = $this->normalizeSocialLinks($config['platform_social_links'] ?? []);

        $dir = dirname($this->configPath);
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }

        file_put_contents($this->configPath, json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        $this->cache->delete('platform.app_config');

        return $config;
    }

    /** @return list<string> */
    public function validate(array $data): array
    {
        $errors = $this->validateAgainstSchema($data, $this->defaults());

        if (isset($data['platform_social_links']) && is_array($data['platform_social_links'])) {
            foreach ($data['platform_social_links'] as $network => $value) {
                if (
                    is_string($value)
                    && '' !== trim($value)
                    && 'whatsapp' !== $network
                    && !str_starts_with(strtolower(trim($value)), 'https://')
                ) {
                    $errors[] = sprintf('platform_social_links.%s must use https://', $network);
                }
            }
        }

        return $errors;
    }

    /** @return array<string, mixed> */
    public function publicConfig(): array
    {
        $config = $this->get();

        return [
            'platformName' => $config['platform_name'] ?? 'Hanooti',
            'platformLogo' => $config['platform_logo'] ?? null,
            'platformFavicon' => $config['platform_favicon'] ?? null,
            'supportEmail' => $config['support_email'] ?? null,
            'supportPhone' => $config['support_phone'] ?? null,
            'supportAddress' => $config['support_address'] ?? null,
            'socialLinks' => $config['platform_social_links'] ?? [],
        ];
    }

    /** @return array<string, mixed> */
    public function defaults(): array
    {
        return [
            'platform_name' => 'Hanooti',
            'platform_logo' => null,
            'platform_favicon' => null,
            'support_email' => null,
            'support_phone' => null,
            'support_address' => null,
            'platform_social_links' => [
                'facebook' => '',
                'instagram' => '',
                'tiktok' => '',
                'youtube' => '',
                'linkedin' => '',
                'x_twitter' => '',
                'whatsapp' => '',
            ],
            'timezone' => 'Africa/Tunis',
            'default_language' => 'fr',
            'default_currency' => 'TND',
            'modules' => [
                'boutiques' => true,
                'produits' => true,
                'categories' => true,
                'commandes' => true,
                'paiements' => true,
                'livraison' => true,
                'promotions' => true,
                'coupons' => true,
                'cms' => true,
                'blog' => true,
                'avis' => true,
                'fidelite' => true,
                'wallet' => false,
                'seo' => true,
                'facturation' => true,
                'notifications' => true,
            ],
            'authentication' => [
                'registration_enabled' => true,
                'google_login_enabled' => false,
                'facebook_login_enabled' => false,
                'apple_login_enabled' => false,
                'email_verification_required' => false,
                'two_factor_enabled' => false,
            ],
            'boutiques' => [
                'validation_required' => true,
                'auto_create_boutique' => false,
                'subdomains_enabled' => false,
                'custom_domains_enabled' => true,
                'max_boutiques_per_admin' => 1,
            ],
            'subscriptions' => [
                'enabled' => true,
                'free_plan_enabled' => true,
                'default_plan_duration_months' => 12,
                'auto_renewal_enabled' => false,
                'expiration_reminder_days' => 7,
            ],
            'payments' => [
                'visible_methods' => [],
                'cash_on_delivery_enabled' => true,
                'online_payment_enabled' => true,
                'bank_transfer_enabled' => true,
            ],
            'delivery' => [
                'visible_companies' => [],
                'global_free_delivery_enabled' => false,
                'default_settings' => [],
            ],
            'customer_fields' => [
                'firstname' => ['visible' => true, 'required' => true],
                'lastname' => ['visible' => true, 'required' => true],
                'phone' => ['visible' => true, 'required' => true],
                'address' => ['visible' => true, 'required' => true],
                'city' => ['visible' => true, 'required' => true],
                'postal_code' => ['visible' => true, 'required' => false],
                'company' => ['visible' => false, 'required' => false],
            ],
            'notifications' => [
                'email' => true,
                'sms' => false,
                'push' => false,
                'dashboard' => true,
            ],
            'sessions' => [
                'max_sessions' => 0,
                'limit_behavior' => 'REJECT',
                'invalidate_other_sessions_on_password_change' => true,
                'ttl_days' => 30,
                'remember_me_enabled' => false,
                'allow_current_session_deletion' => false,
            ],
            'cache' => ['ttl_seconds' => 21600],
            'legacy' => [
                'app_meta_pixel_id' => '',
                'enable_email_verification' => false,
                'enable_loyalty_module' => false,
                'loyalty_default_points_per_amount' => 1,
                'loyalty_default_amount_cents' => 100,
            ],
        ];
    }

    public function isModuleEnabled(string $key): bool
    {
        return (bool) ($this->get()['modules'][$key] ?? true);
    }

    /** @return array<string, mixed> */
    public function section(string $name): array
    {
        $section = $this->get()[$name] ?? [];

        return is_array($section) ? $section : [];
    }

    /** @return array<string, mixed> */
    private function readFromDisk(): array
    {
        if (!is_file($this->configPath)) {
            return $this->defaults();
        }

        $content = file_get_contents($this->configPath);
        $data = json_decode($content ?: '', true);

        return is_array($data) ? $this->mergeConfig($this->defaults(), $data, $this->defaults()) : $this->defaults();
    }

    /** @param array<string, mixed> $links @return array<string, string> */
    private function normalizeSocialLinks(array $links): array
    {
        $normalized = [];
        foreach ($links as $network => $value) {
            if (!is_string($value)) {
                continue;
            }

            $value = trim($value);
            if ('' !== $value) {
                $normalized[(string) $network] = $value;
            }
        }

        return $normalized;
    }

    /** @param array<string, mixed> $base @param array<string, mixed> $updates @param array<string, mixed> $schema @return array<string, mixed> */
    private function mergeConfig(array $base, array $updates, array $schema): array
    {
        foreach ($updates as $key => $value) {
            if (!array_key_exists($key, $schema)) {
                continue;
            }
            if (is_array($schema[$key]) && is_array($value) && is_array($base[$key] ?? null)) {
                $base[$key] = $this->mergeConfig($base[$key], $value, $schema[$key]);
                continue;
            }
            $base[$key] = $value;
        }

        return $base;
    }

    /** @param array<string, mixed> $data @param array<string, mixed> $schema @return list<string> */
    private function validateAgainstSchema(array $data, array $schema, string $prefix = ''): array
    {
        $errors = [];
        foreach ($data as $key => $value) {
            if (!array_key_exists($key, $schema)) {
                $errors[] = sprintf('Unknown key: %s%s', $prefix, $key);
                continue;
            }
            $expected = $schema[$key];
            $path = $prefix.$key;
            if (is_array($expected)) {
                if (!is_array($value)) {
                    $errors[] = sprintf('Expected object/array at %s', $path);
                    continue;
                }
                $errors = [...$errors, ...$this->validateAgainstSchema($value, $expected, $path.'.')];
                continue;
            }
            if (null === $value || null === $expected) {
                continue;
            }
            $type = gettype($expected);
            if (gettype($value) !== $type) {
                $errors[] = sprintf('Expected %s at %s', $type, $path);
            }
        }

        return $errors;
    }
}
