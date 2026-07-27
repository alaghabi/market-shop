<?php

namespace App\EventSubscriber;

use App\Event\MessageSentEvent;
use App\Message\ChatbotQueryMessage;
use App\Repository\ChatbotConfigRepository;
use App\Service\Chat\MercurePublisher;
use App\Service\Chat\MessageNotificationService;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Messenger\MessageBusInterface;

final class MessageEventSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private MercurePublisher $mercurePublisher,
        private MessageNotificationService $notificationService,
        private ChatbotConfigRepository $configRepository,
        private MessageBusInterface $messageBus,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            MessageSentEvent::class => [
                ['publishToMercure', 10],
                ['sendNotification', 0],
                ['dispatchChatbotResponse', -10],
            ],
        ];
    }

    public function publishToMercure(MessageSentEvent $event): void
    {
        $this->mercurePublisher->publishMessage($event->getMessage());
    }

    public function sendNotification(MessageSentEvent $event): void
    {
        $message = $event->getMessage();

        if ('user' === $message->getSenderType()) {
            $this->notificationService->notifyAdmins(
                $message->getConversation(),
                $message->getContent(),
            );
        }
    }

    public function dispatchChatbotResponse(MessageSentEvent $event): void
    {
        $message = $event->getMessage();

        if ('user' !== $message->getSenderType()) {
            return;
        }

        $conversation = $message->getConversation();
        $boutique = $conversation->getBoutique();

        $config = $this->configRepository->findEnabledByBoutique($boutique);
        if (null === $config) {
            return;
        }

        $this->messageBus->dispatch(new ChatbotQueryMessage(
            conversationId: (string) $conversation->getId(),
            boutiqueId: (string) $boutique->getId(),
            userMessage: $message->getContent(),
            userMessageId: (string) $message->getId(),
        ));
    }
}
