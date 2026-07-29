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
                if ('email_verification' === $message->eventCode) {
                    $this->sendEmailVerification($message, $boutique);
                } elseif ('password_reset' === $message->eventCode) {
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
        $rendered = $template?->getContent() ?? $this->defaultMessage($message->eventCode, $message->variables);
        foreach ($message->variables as $key => $value) {
            $rendered = str_replace('{{'.$key.'}}', (string) $value, $rendered);
        }

        $subject = $template?->getSubject() ?? $this->defaultSubject($message->eventCode);
        foreach ($message->variables as $key => $value) {
            $subject = str_replace('{{'.$key.'}}', (string) $value, (string) $subject);
        }

        $html = $this->twig->render('email/notification.html.twig', [
            ...$this->brandContext($boutique),
            'message' => $rendered,
        ]);

        $email = (new TemplatedEmail())
                ->from($this->mailerFrom)
                ->to($message->recipient)
                ->subject((string) $subject)
                ->html($html)
                ->text(strip_tags($rendered));
        $this->mailer->send($this->withBoutiqueReplyTo($email, $boutique));
    }

    private function sendEmailVerification(DispatchNotificationMessage $message, ?\App\Entity\Boutique $boutique): void
    {
        $verificationUrl = (string) ($message->variables['verificationUrl'] ?? '#');

        $email = (new TemplatedEmail())
                ->from($this->mailerFrom)
                ->to($message->recipient)
                ->subject('Vérifiez votre adresse email')
                ->htmlTemplate('email/email_verification.html.twig')
                ->context([
                    ...$this->brandContext($boutique),
                    'name' => $message->variables['name'] ?? 'Utilisateur',
                    'verificationUrl' => $verificationUrl,
                ])
                ->text('Vérifiez votre adresse email : '.$verificationUrl);
        $this->mailer->send($this->withBoutiqueReplyTo($email, $boutique));
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
                ...$this->brandContext($boutique),
                'orderReference' => (string) $order->getId(),
                'orderDate' => $order->getCreatedAt(),
                'customerName' => $order->getCustomerName(),
                'items' => $items,
                'totalCents' => $order->getTotalCents(),
                'currency' => $order->getCurrency(),
            ]);

        $this->mailer->send($this->withBoutiqueReplyTo($email, $boutique));
    }

    private function sendPasswordResetEmail(DispatchNotificationMessage $message, ?\App\Entity\Boutique $boutique): void
    {
        $email = (new TemplatedEmail())
                ->from($this->mailerFrom)
                ->to($message->recipient)
                ->subject('Réinitialisation de votre mot de passe')
                ->htmlTemplate('email/password_reset.html.twig')
                ->context([
                    ...$this->brandContext($boutique),
                    'name' => $message->variables['name'] ?? 'Utilisateur',
                    'resetUrl' => $message->variables['resetUrl'] ?? '#',
                ]);
        $this->mailer->send($this->withBoutiqueReplyTo($email, $boutique));
    }

    private function withBoutiqueReplyTo(TemplatedEmail $email, ?\App\Entity\Boutique $boutique): TemplatedEmail
    {
        $contactEmail = $boutique?->getContactEmail();
        if (null !== $contactEmail && false !== filter_var($contactEmail, FILTER_VALIDATE_EMAIL)) {
            $email->replyTo($contactEmail);
        }

        return $email;
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
            'employee.activated' => 'Votre accès employé boutique est activé',
            'employee.suspended' => 'Votre accès employé boutique est suspendu',
            'subscription.approved' => 'Votre abonnement boutique est accepté',
            'subscription.rejected' => 'Votre demande d’abonnement est refusée',
            'boutique.published' => 'Votre boutique est publiée',
            'boutique.unpublished' => 'Votre boutique est dépubliée',
            'boutique.publication_requested' => 'Demande de publication reçue',
            'boutique.publication_approved' => 'Votre boutique est publiée',
            'boutique.publication_rejected' => 'Votre demande de publication est refusée',
            'subscription.expired' => 'Votre abonnement a expiré',
            default => 'Notification Hanooti',
        };
    }

    /** @param array<string, string|int|float|bool|null> $variables */
    private function defaultMessage(string $eventCode, array $variables = []): string
    {
        $message = match ($eventCode) {
            'boutique.approved' => 'Votre boutique a été approuvée par Hanooti.',
            'boutique.rejected' => 'Votre boutique a été refusée par Hanooti.',
            'boutique.suspended' => 'Votre boutique a été suspendue par Hanooti.',
            'boutique.activated' => 'Votre boutique a été réactivée par Hanooti.',
            'boutique.archived' => 'Votre boutique a été archivée par Hanooti.',
            'boutique_admin.activated' => 'Votre accès administrateur à la boutique a été activé.',
            'boutique_admin.suspended' => 'Votre accès administrateur à la boutique a été suspendu.',
            'employee.activated' => 'Votre accès employé à la boutique a été activé.',
            'employee.suspended' => 'Votre accès employé à la boutique a été suspendu.',
            'subscription.approved' => 'Votre demande d’abonnement a été acceptée.',
            'subscription.rejected' => 'Votre demande d’abonnement a été refusée.',
            'boutique.published' => 'Votre boutique est maintenant visible publiquement.',
            'boutique.unpublished' => 'Votre boutique n’est plus visible publiquement.',
            'boutique.publication_requested' => 'La demande de publication de votre boutique a été reçue et sera traitée sous 24h.',
            'boutique.publication_approved' => 'Votre boutique est maintenant visible publiquement.',
            'boutique.publication_rejected' => 'Votre demande de publication a été refusée.',
            'subscription.expired' => 'Votre abonnement boutique a expiré. Renouvelez-le pour réactiver vos fonctionnalités.',
            default => 'Une nouvelle notification est disponible dans votre back-office.',
        };

        $reason = $variables['reason'] ?? null;
        if (is_string($reason) && '' !== trim($reason)) {
            $message .= ' Raison : '.$reason;
        }

        return $message;
    }

    /** @return array<string, mixed> */
    private function brandContext(?\App\Entity\Boutique $boutique): array
    {
        $settings = $boutique?->getSettings();

        return [
            'boutiqueName' => $boutique?->getName() ?? 'Hanooti',
            'logoUrl' => $this->absoluteUrl($boutique?->getLogoUrl()),
            'slogan' => $settings?->getSlogan(),
            'primaryColor' => $this->safeColor($boutique?->getPrimaryColor() ?? '#3525cd', '#3525cd'),
            'secondaryColor' => $this->safeColor($boutique?->getSecondaryColor() ?? '#505f76', '#505f76'),
            'fontFamily' => $this->safeFont($settings?->getFontFamily()),
            'fontSize' => $this->safeSize($settings?->getFontSize(), '15px'),
            'borderRadius' => $this->safeSize($settings?->getBorderRadius(), '12px'),
            'footerText' => $settings?->getFooterConfig()['footer_text'] ?? 'Email envoyé par Hanooti',
        ];
    }

    private function safeColor(string $value, string $fallback): string
    {
        return preg_match('/^#[0-9a-fA-F]{3,8}$/', $value) ? $value : $fallback;
    }

    private function safeFont(?string $value): string
    {
        $allowed = ['Arial', 'Helvetica', 'Inter', 'Roboto', 'Open Sans', 'sans-serif'];

        return is_string($value) && in_array($value, $allowed, true) ? $value : 'Arial, sans-serif';
    }

    private function safeSize(?string $value, string $fallback): string
    {
        return is_string($value) && preg_match('/^\d{1,3}(px|rem|em|%)$/', $value) ? $value : $fallback;
    }
}
