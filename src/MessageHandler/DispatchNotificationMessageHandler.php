<?php

namespace App\MessageHandler;

use App\Entity\Order;
use App\Entity\NotificationLog;
use App\Entity\ProductImage;
use App\Enum\NotificationChannel;
use App\Enum\NotificationLogStatus;
use App\Message\DispatchNotificationMessage;
use App\Repository\BoutiqueRepository;
use App\Repository\NotificationTemplateRepository;
use App\Repository\OrderRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Twig\Environment;

#[AsMessageHandler]
final readonly class DispatchNotificationMessageHandler
{
    public function __construct(
        private NotificationTemplateRepository $templates,
        private BoutiqueRepository $boutiques,
        private OrderRepository $orders,
        private EntityManagerInterface $em,
        private MailerInterface $mailer,
        private Environment $twig,
        private string $mailerFrom,
        private string $publicBaseUrl,
    ) {
    }

    public function __invoke(DispatchNotificationMessage $message): void
    {
        $boutique = $message->boutiqueId ? $this->boutiques->findBySlugOrId($message->boutiqueId) : null;
        $channel = NotificationChannel::tryFrom($message->channel) ?? NotificationChannel::Internal;
        $log = null !== $message->deduplicationKey
            ? $this->em->getRepository(NotificationLog::class)->findOneBy(['deduplicationKey' => $message->deduplicationKey])
            : null;
        if ($log instanceof NotificationLog && NotificationLogStatus::Sent === $log->getStatus()) {
            return;
        }

        $log ??= new NotificationLog($boutique, $channel, $message->recipient, $message->eventCode, NotificationLogStatus::Pending, null, null, new \DateTimeImmutable(), $message->deduplicationKey);
        $this->em->persist($log);

        try {
            $template = $this->templates->findActiveTemplate($boutique, $message->eventCode, $channel);
            if (NotificationChannel::Email !== $channel) {
                if (null === $template) {
                    $log->markFailed('Template not found');
                    $this->em->flush();

                    return;
                }

                $log->markSent();
                $this->em->flush();

                return;
            }

            if (null === $message->orderId) {
                if ('password_reset' === $message->eventCode) {
                    $this->sendPasswordResetEmail($message, $boutique);
                } else {
                    $this->sendGenericEmail($message, $boutique, $template);
                }
            } else {
                $this->sendOrderConfirmation($message);
            }

            $log->markSent();
        } catch (\Throwable $e) {
            $log->markFailed($e->getMessage());
            $this->em->flush();
            throw $e;
        }

        $this->em->flush();
    }

    private function sendGenericEmail(DispatchNotificationMessage $message, ?\App\Entity\Boutique $boutique, ?\App\Entity\NotificationTemplate $template): void
    {
        $rendered = $template?->getContent() ?? $this->defaultMessage($message->eventCode);
        foreach ($message->variables as $key => $value) {
            $rendered = str_replace('{{'.$key.'}}', (string) $value, $rendered);
        }

        $subject = $template?->getSubject() ?? $this->defaultSubject($message->eventCode);
        foreach ($message->variables as $key => $value) {
            $subject = str_replace('{{'.$key.'}}', (string) $value, (string) $subject);
        }

        $html = $this->twig->render('email/notification.html.twig', [
            'boutique' => $boutique,
            'logoUrl' => $this->absoluteUrl($boutique?->getLogoUrl()),
            'message' => $rendered,
        ]);

        $this->mailer->send(
            (new TemplatedEmail())
                ->from($this->mailerFrom)
                ->to($message->recipient)
                ->subject((string) $subject)
                ->html($html)
                ->text(strip_tags($rendered)),
        );
    }

    private function sendOrderConfirmation(DispatchNotificationMessage $message): void
    {
        $order = $this->orders->find($message->orderId);
        if (!$order instanceof Order) {
            throw new \RuntimeException('Order not found for notification.');
        }

        $boutique = $order->getBoutique();
        $items = [];
        foreach ($order->getItems() as $item) {
            $image = $item->getVariant()?->getImage();
            if (null === $image || '' === $image) {
                $productImage = $item->getProduct()?->getImages()->first();
                $image = $productImage instanceof ProductImage ? ($productImage->getSmallUrl() ?: $productImage->getUrl()) : null;
            }
            $items[] = [
                'name' => $item->getProductName(),
                'sku' => $item->getSku(),
                'quantity' => $item->getQuantity(),
                'unitPriceCents' => $item->getUnitPriceCents(),
                'totalCents' => $item->getUnitPriceCents() * $item->getQuantity(),
                'image' => $this->absoluteUrl($image),
            ];
        }

        $email = (new TemplatedEmail())
            ->from($this->mailerFrom)
            ->to($message->recipient)
            ->subject('Confirmation de votre commande #'.substr((string) $order->getId(), 0, 8))
            ->htmlTemplate('email/order_confirmation.html.twig')
            ->context([
                'boutiqueName' => $boutique->getName(),
                'logoUrl' => $this->absoluteUrl($boutique->getLogoUrl()),
                'orderReference' => (string) $order->getId(),
                'orderDate' => $order->getCreatedAt(),
                'customerName' => $order->getCustomerName(),
                'items' => $items,
                'totalCents' => $order->getTotalCents(),
                'currency' => $order->getCurrency(),
            ]);

        $this->mailer->send($email);
    }

    private function sendPasswordResetEmail(DispatchNotificationMessage $message, ?\App\Entity\Boutique $boutique): void
    {
        $this->mailer->send(
            (new TemplatedEmail())
                ->from($this->mailerFrom)
                ->to($message->recipient)
                ->subject('Réinitialisation de votre mot de passe')
                ->htmlTemplate('email/password_reset.html.twig')
                ->context([
                    'boutique' => $boutique,
                    'logoUrl' => $this->absoluteUrl($boutique?->getLogoUrl()),
                    'name' => $message->variables['name'] ?? 'Utilisateur',
                    'resetUrl' => $message->variables['resetUrl'] ?? '#',
                ]),
        );
    }

    private function absoluteUrl(?string $url): ?string
    {
        if (null === $url || '' === trim($url)) {
            return null;
        }

        return null !== parse_url($url, PHP_URL_SCHEME)
            ? $url
            : rtrim($this->publicBaseUrl, '/').'/'.ltrim($url, '/');
    }

    private function defaultSubject(string $eventCode): string
    {
        return match ($eventCode) {
            'boutique.approved' => 'Votre boutique est approuvée',
            'boutique.rejected' => 'Votre boutique est refusée',
            'boutique.suspended' => 'Votre boutique est suspendue',
            'boutique.activated' => 'Votre boutique est activée',
            'boutique.archived' => 'Votre boutique est archivée',
            'boutique_admin.activated' => 'Votre accès administrateur boutique est activé',
            'boutique_admin.suspended' => 'Votre accès administrateur boutique est suspendu',
            'subscription.approved' => 'Votre abonnement boutique est accepté',
            'subscription.rejected' => 'Votre demande d’abonnement est refusée',
            'boutique.published' => 'Votre boutique est publiée',
            'boutique.unpublished' => 'Votre boutique est dépubliée',
            default => 'Notification Hanooti',
        };
    }

    private function defaultMessage(string $eventCode): string
    {
        return match ($eventCode) {
            'boutique.approved' => 'Votre boutique a été approuvée par Hanooti.',
            'boutique.rejected' => 'Votre boutique a été refusée par Hanooti.',
            'boutique.suspended' => 'Votre boutique a été suspendue par Hanooti.',
            'boutique.activated' => 'Votre boutique a été réactivée par Hanooti.',
            'boutique.archived' => 'Votre boutique a été archivée par Hanooti.',
            'boutique_admin.activated' => 'Votre accès administrateur à la boutique a été activé.',
            'boutique_admin.suspended' => 'Votre accès administrateur à la boutique a été suspendu.',
            'subscription.approved' => 'Votre demande d’abonnement a été acceptée.',
            'subscription.rejected' => 'Votre demande d’abonnement a été refusée.',
            'boutique.published' => 'Votre boutique est maintenant visible publiquement.',
            'boutique.unpublished' => 'Votre boutique n’est plus visible publiquement.',
            default => 'Une nouvelle notification est disponible dans votre back-office.',
        };
    }
}
