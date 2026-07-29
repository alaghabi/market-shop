<?php

namespace App\Service\Delivery;

use App\Entity\Boutique;
use App\Entity\Order;
use App\Enum\PaymentMethodType;

/**
 * Catalog of internal variables exposed to the dynamic mapping engine, and
 * resolver that turns an Order into a flat "dot notation" context array.
 */
final class DeliveryVariableRegistry
{
    /**
     * @return list<array{code: string, label: string, category: string}>
     */
    public function catalog(): array
    {
        return [
            ['code' => 'order.number', 'label' => 'Numéro de commande', 'category' => 'order'],
            ['code' => 'order.total', 'label' => 'Montant total (TND)', 'category' => 'order'],
            ['code' => 'order.total_cents', 'label' => 'Montant total (centimes)', 'category' => 'order'],
            ['code' => 'order.shipping_cost', 'label' => 'Frais de livraison', 'category' => 'order'],
            ['code' => 'order.payment_method', 'label' => 'Méthode de paiement', 'category' => 'order'],
            ['code' => 'order.weight', 'label' => 'Poids total (kg)', 'category' => 'order'],
            ['code' => 'order.currency', 'label' => 'Devise', 'category' => 'order'],
            ['code' => 'order.created_at', 'label' => 'Date de commande', 'category' => 'order'],
            ['code' => 'order.cod_amount', 'label' => 'Montant contre-remboursement', 'category' => 'order'],

            ['code' => 'customer.first_name', 'label' => 'Prénom client', 'category' => 'customer'],
            ['code' => 'customer.last_name', 'label' => 'Nom client', 'category' => 'customer'],
            ['code' => 'customer.full_name', 'label' => 'Nom complet client', 'category' => 'customer'],
            ['code' => 'customer.phone', 'label' => 'Téléphone client', 'category' => 'customer'],
            ['code' => 'customer.email', 'label' => 'Email client', 'category' => 'customer'],

            ['code' => 'address.street', 'label' => 'Adresse (rue)', 'category' => 'address'],
            ['code' => 'address.city', 'label' => 'Ville', 'category' => 'address'],
            ['code' => 'address.postal_code', 'label' => 'Code postal', 'category' => 'address'],
            ['code' => 'address.country', 'label' => 'Pays', 'category' => 'address'],
            ['code' => 'address.governorate', 'label' => 'Gouvernorat', 'category' => 'address'],
            ['code' => 'address.full_address', 'label' => 'Adresse complète', 'category' => 'address'],

            ['code' => 'shop.name', 'label' => 'Nom de la boutique', 'category' => 'shop'],
            ['code' => 'shop.phone', 'label' => 'Téléphone boutique', 'category' => 'shop'],
            ['code' => 'shop.address', 'label' => 'Adresse boutique', 'category' => 'shop'],
            ['code' => 'shop.email', 'label' => 'Email boutique', 'category' => 'shop'],

            ['code' => 'products.list', 'label' => 'Liste des produits', 'category' => 'products'],
            ['code' => 'products.quantity', 'label' => 'Quantité totale', 'category' => 'products'],
            ['code' => 'products.weight', 'label' => 'Poids total produits (kg)', 'category' => 'products'],
            ['code' => 'products.count', 'label' => 'Nombre de lignes produit', 'category' => 'products'],
        ];
    }

    /** @return array<string, mixed> flat dot-notation context resolved from a real order */
    public function resolveContext(Order $order): array
    {
        $boutique = $order->getBoutique();
        $items = $order->getItems();

        $quantity = 0;
        $weightGrams = 0;
        $productLines = [];
        foreach ($items as $item) {
            $quantity += $item->getQuantity();
            $product = $item->getProduct();
            if (null !== $product) {
                $weightGrams += $product->getWeight() * $item->getQuantity();
            }
            $productLines[] = [
                'sku' => $item->getSku(),
                'name' => $item->getProductName(),
                'quantity' => $item->getQuantity(),
                'unit_price' => round($item->getUnitPriceCents() / 100, 3),
            ];
        }

        $firstName = '';
        $lastName = '';
        if (null !== $order->getCustomer()) {
            $firstName = $order->getCustomer()->getFirstName() ?? '';
            $lastName = $order->getCustomer()->getLastName() ?? '';
        }
        $fullName = trim($firstName.' '.$lastName);
        if ('' === $fullName) {
            $fullName = (string) $order->getCustomerName();
        }

        $addressParts = array_filter([
            $order->getShippingAddress(),
            $order->getShippingCity(),
            $order->getShippingPostalCode(),
        ]);

        return [
            'order.number' => (string) $order->getId(),
            'order.total' => round($order->getTotalCents() / 100, 3),
            'order.total_cents' => $order->getTotalCents(),
            'order.shipping_cost' => 0,
            'order.payment_method' => $order->getPaymentMethodCode() ?? '',
            'order.weight' => round($weightGrams / 1000, 3),
            'order.currency' => $order->getCurrency(),
            'order.created_at' => $order->getCreatedAt()->format('c'),
            'order.cod_amount' => PaymentMethodType::CashOnDelivery->value === strtoupper((string) $order->getPaymentMethodCode())
                ? round($order->getTotalCents() / 100, 3)
                : 0,

            'customer.first_name' => $firstName,
            'customer.last_name' => $lastName,
            'customer.full_name' => $fullName,
            'customer.phone' => $order->getCustomerPhone() ?? '',
            'customer.email' => $order->getCustomerEmail() ?? '',

            'address.street' => $order->getShippingAddress() ?? '',
            'address.city' => $order->getShippingCity() ?? '',
            'address.postal_code' => $order->getShippingPostalCode() ?? '',
            'address.country' => $order->getShippingCountry() ?? '',
            'address.governorate' => $order->getShippingGovernorate() ?? '',
            'address.full_address' => implode(', ', $addressParts),

            'shop.name' => $boutique->getName(),
            'shop.phone' => $boutique->getPhone() ?? '',
            'shop.address' => $boutique->getAddress() ?? '',
            'shop.email' => $boutique->getEmail() ?? '',

            'products.list' => $productLines,
            'products.quantity' => $quantity,
            'products.weight' => round($weightGrams / 1000, 3),
            'products.count' => count($productLines),
        ];
    }

    /** @return array<string, mixed> sample context used to preview/test a mapping without a real order */
    public function sampleContext(?Boutique $boutique = null): array
    {
        return [
            'order.number' => 'CMD-0001',
            'order.total' => 89.9,
            'order.total_cents' => 8990,
            'order.shipping_cost' => 7.0,
            'order.payment_method' => 'cash_on_delivery',
            'order.weight' => 1.2,
            'order.currency' => 'TND',
            'order.created_at' => (new \DateTimeImmutable())->format('c'),
            'order.cod_amount' => 89.9,

            'customer.first_name' => 'Amine',
            'customer.last_name' => 'Trabelsi',
            'customer.full_name' => 'Amine Trabelsi',
            'customer.phone' => '+21620123456',
            'customer.email' => 'amine.trabelsi@example.com',

            'address.street' => 'Rue de la Liberté 12',
            'address.city' => 'Tunis',
            'address.postal_code' => '1000',
            'address.country' => 'Tunisie',
            'address.governorate' => 'Tunis',
            'address.full_address' => 'Rue de la Liberté 12, Tunis, 1000',

            'shop.name' => $boutique?->getName() ?? 'Ma Boutique',
            'shop.phone' => $boutique?->getPhone() ?? '+21671000000',
            'shop.address' => $boutique?->getAddress() ?? 'Avenue Habib Bourguiba, Tunis',
            'shop.email' => $boutique?->getEmail() ?? 'contact@boutique.tn',

            'products.list' => [
                ['sku' => 'SKU-1', 'name' => 'Produit démo', 'quantity' => 2, 'unit_price' => 44.95],
            ],
            'products.quantity' => 2,
            'products.weight' => 1.2,
            'products.count' => 1,
        ];
    }

    /** @return list<string> all variable codes known to the catalog, for import validation */
    public function knownCodes(): array
    {
        return array_map(static fn (array $entry) => $entry['code'], $this->catalog());
    }
}
