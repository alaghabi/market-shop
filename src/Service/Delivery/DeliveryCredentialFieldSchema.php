<?php

namespace App\Service\Delivery;

use App\Dto\Delivery\BoutiqueDeliveryAccountInput;
use App\Entity\BoutiqueDeliveryAccount;
use App\Entity\DeliveryCompany;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * Resolves and validates dynamic boutique credential fields declared in
 * DeliveryCompany.authConfig.credentialFields.
 */
final class DeliveryCredentialFieldSchema
{
    public const ALLOWED_KEYS = ['login', 'password', 'apiKey', 'token', 'secret', 'customBaseUrl'];

    /**
     * @return list<array{key: string, label: string, type: string, required: bool, hint: ?string}>
     */
    public function fieldsFor(DeliveryCompany $company): array
    {
        $raw = $company->getAuthConfig()['credentialFields'] ?? null;
        if (!is_array($raw) || [] === $raw) {
            return $this->fallbackFields($company);
        }

        $fields = [];
        foreach ($raw as $field) {
            if (!is_array($field)) {
                continue;
            }
            $key = (string) ($field['key'] ?? '');
            if (!in_array($key, self::ALLOWED_KEYS, true)) {
                continue;
            }
            $fields[] = [
                'key' => $key,
                'label' => (string) ($field['label'] ?? $key),
                'type' => (string) ($field['type'] ?? ('customBaseUrl' === $key ? 'text' : 'password')),
                'required' => (bool) ($field['required'] ?? false),
                'hint' => isset($field['hint']) ? (string) $field['hint'] : null,
            ];
        }

        return [] !== $fields ? $fields : $this->fallbackFields($company);
    }

    public function assertValid(
        DeliveryCompany $company,
        BoutiqueDeliveryAccountInput $input,
        ?BoutiqueDeliveryAccount $existing = null,
    ): void {
        foreach ($this->fieldsFor($company) as $field) {
            if (!$field['required']) {
                continue;
            }

            $key = $field['key'];
            $incoming = $this->inputValue($input, $key);
            if (null !== $incoming && '' !== trim($incoming)) {
                continue;
            }

            if (null !== $existing && $this->hasStoredValue($existing, $key)) {
                continue;
            }

            throw new BadRequestHttpException(sprintf('Le champ « %s » est obligatoire pour %s.', $field['label'], $company->getName()));
        }
    }

    /**
     * @return list<array{key: string, label: string, type: string, required: bool, hint: ?string}>
     */
    private function fallbackFields(DeliveryCompany $company): array
    {
        return match ($company->getAuthType()->value) {
            'bearer' => [[
                'key' => 'token',
                'label' => 'Jeton API',
                'type' => 'password',
                'required' => true,
                'hint' => null,
            ]],
            'api_key' => [[
                'key' => 'apiKey',
                'label' => 'Clé API',
                'type' => 'password',
                'required' => true,
                'hint' => null,
            ]],
            'basic' => [
                [
                    'key' => 'login',
                    'label' => 'Identifiant',
                    'type' => 'text',
                    'required' => true,
                    'hint' => null,
                ],
                [
                    'key' => 'password',
                    'label' => 'Mot de passe',
                    'type' => 'password',
                    'required' => true,
                    'hint' => null,
                ],
            ],
            default => [
                [
                    'key' => 'login',
                    'label' => 'Identifiant',
                    'type' => 'text',
                    'required' => false,
                    'hint' => null,
                ],
                [
                    'key' => 'password',
                    'label' => 'Mot de passe',
                    'type' => 'password',
                    'required' => false,
                    'hint' => null,
                ],
                [
                    'key' => 'apiKey',
                    'label' => 'Clé API',
                    'type' => 'password',
                    'required' => false,
                    'hint' => null,
                ],
                [
                    'key' => 'token',
                    'label' => 'Token',
                    'type' => 'password',
                    'required' => false,
                    'hint' => null,
                ],
                [
                    'key' => 'secret',
                    'label' => 'Secret',
                    'type' => 'password',
                    'required' => false,
                    'hint' => null,
                ],
                [
                    'key' => 'customBaseUrl',
                    'label' => 'URL personnalisée',
                    'type' => 'text',
                    'required' => false,
                    'hint' => 'Optionnel',
                ],
            ],
        };
    }

    private function inputValue(BoutiqueDeliveryAccountInput $input, string $key): ?string
    {
        return match ($key) {
            'login' => $input->login,
            'password' => $input->password,
            'apiKey' => $input->apiKey,
            'token' => $input->token,
            'secret' => $input->secret,
            'customBaseUrl' => $input->customBaseUrl,
            default => null,
        };
    }

    private function hasStoredValue(BoutiqueDeliveryAccount $account, string $key): bool
    {
        return match ($key) {
            'login' => '' !== $account->getEncryptedLogin(),
            'password' => '' !== $account->getEncryptedPassword(),
            'apiKey' => null !== $account->getEncryptedApiKey() && '' !== $account->getEncryptedApiKey(),
            'token' => null !== $account->getEncryptedToken() && '' !== $account->getEncryptedToken(),
            'secret' => null !== $account->getEncryptedSecret() && '' !== $account->getEncryptedSecret(),
            'customBaseUrl' => null !== $account->getCustomBaseUrl() && '' !== $account->getCustomBaseUrl(),
            default => false,
        };
    }
}
