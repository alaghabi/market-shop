<?php

namespace App\State\Invoice;

use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use ApiPlatform\State\Pagination\PaginatorInterface;
use App\Dto\Invoice\InvoiceOutput;
use App\Entity\Invoice;
use App\Repository\BoutiqueRepository;
use App\Repository\InvoiceRepository;
use App\Security\BoutiqueContext;
use App\Service\Backoffice\BackofficeScopeResolver;
use App\Service\Billing\InvoiceCacheService;
use App\State\Common\BoutiqueAwareProviderTrait;
use App\State\Common\BackofficePaginator;
use Symfony\Component\HttpFoundation\Request;

/** @implements ProviderInterface<InvoiceOutput> */
final readonly class InvoiceProvider implements ProviderInterface
{
    use BoutiqueAwareProviderTrait;

    public function __construct(
        private InvoiceRepository $invoices,
        private BoutiqueRepository $boutiques,
        private BoutiqueContext $context,
        private InvoiceCacheService $cache,
        private BackofficeScopeResolver $scope,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): PaginatorInterface|InvoiceOutput|null
    {
        $request = $context['request'] ?? null;
        $request = $request instanceof Request ? $request : null;
        $pagination = $this->scope->pagination($request);
        $invoiceId = $uriVariables['id'] ?? null;
        if ($operation instanceof Get && null !== $invoiceId) {
            $invoice = $this->invoices->find((string) $invoiceId);
            if (!$invoice instanceof Invoice || !$this->canReadInvoice($invoice, $context, $operation)) {
                return null;
            }

            return $this->cache->getInvoice((string) $invoiceId, function () use ($invoiceId): ?InvoiceOutput {
                $invoice = $this->invoices->find((string) $invoiceId);

                return $invoice instanceof Invoice ? $this->toOutput($invoice) : null;
            });
        }

        if (str_starts_with((string) $operation->getUriTemplate(), '/admin/')) {
            if (!$this->context->isSuperAdmin()) {
                return new BackofficePaginator([], $pagination['page'], $pagination['itemsPerPage'], 0);
            }

            $result = $this->invoices->findForBackoffice(
                null,
                $pagination['page'],
                $pagination['itemsPerPage'],
            );

            return new BackofficePaginator(
                array_map([$this, 'toOutput'], $result['items']),
                $pagination['page'],
                $pagination['itemsPerPage'],
                $result['total'],
            );
        }

        $boutique = $this->resolveBoutiqueFromRequest($context, $uriVariables);
        if (!$boutique instanceof \App\Entity\Boutique) {
            return new BackofficePaginator([], $pagination['page'], $pagination['itemsPerPage'], 0);
        }

        $result = $this->invoices->findForBackoffice(
            $boutique,
            $pagination['page'],
            $pagination['itemsPerPage'],
        );

        return new BackofficePaginator(
            array_map([$this, 'toOutput'], $result['items']),
            $pagination['page'],
            $pagination['itemsPerPage'],
            $result['total'],
        );
    }

    private function canReadInvoice(Invoice $invoice, array $context, Operation $operation): bool
    {
        if ($this->context->isSuperAdmin() && str_starts_with((string) $operation->getUriTemplate(), '/admin/')) {
            return true;
        }

        $boutique = $this->resolveBoutiqueFromRequest($context);

        return $boutique instanceof \App\Entity\Boutique
            && (string) $invoice->getBoutique()->getId() === (string) $boutique->getId();
    }

    private function toOutput(Invoice $invoice): InvoiceOutput
    {
        $output = new InvoiceOutput();
        $output->id = (string) $invoice->getId();
        $output->invoiceNumber = $invoice->getInvoiceNumber();
        $output->boutiqueId = (string) $invoice->getBoutique()->getId();
        $output->customerId = $invoice->getCustomer() ? (string) $invoice->getCustomer()->getId() : null;
        $output->orderId = $invoice->getOrder() ? (string) $invoice->getOrder()->getId() : null;
        $output->subscriptionId = $invoice->getSubscription() ? (string) $invoice->getSubscription()->getId() : null;
        $output->type = $invoice->getType()->value;
        $output->status = $invoice->getStatus()->value;
        $output->currency = $invoice->getCurrency();
        $output->subtotal = $invoice->getSubtotal();
        $output->discountTotal = $invoice->getDiscountTotal();
        $output->taxTotal = $invoice->getTaxTotal();
        $output->shippingTotal = $invoice->getShippingTotal();
        $output->total = $invoice->getTotal();
        $output->issuedAt = $invoice->getIssuedAt()->format('c');
        $output->dueDate = $invoice->getDueDate()?->format('c');
        $output->paidAt = $invoice->getPaidAt()?->format('c');
        $output->pdfPath = $invoice->getPdfPath();
        $output->boutiqueName = $invoice->getBoutiqueName();
        $output->boutiqueEmail = $invoice->getBoutiqueEmail();
        $output->boutiquePhone = $invoice->getBoutiquePhone();
        $output->boutiqueAddress = $invoice->getBoutiqueAddress();
        $output->customerName = $invoice->getCustomerName();
        $output->customerEmail = $invoice->getCustomerEmail();
        $output->customerPhone = $invoice->getCustomerPhone();
        $output->customerAddress = $invoice->getCustomerAddress();
        $output->customerCity = $invoice->getCustomerCity();
        $output->customerPostalCode = $invoice->getCustomerPostalCode();
        $output->customerCountry = $invoice->getCustomerCountry();
        $output->items = array_map(fn ($item) => [
            'productId' => $item->getProduct() ? (string) $item->getProduct()->getId() : null,
            'description' => $item->getDescription(),
            'quantity' => $item->getQuantity(),
            'unitPrice' => $item->getUnitPrice(),
            'discount' => $item->getDiscount(),
            'tax' => $item->getTax(),
            'total' => $item->getTotal(),
        ], $invoice->getItems()->toArray());
        $output->createdAt = $invoice->getCreatedAt()->format('c');
        $output->updatedAt = $invoice->getUpdatedAt()?->format('c');

        return $output;
    }
}
