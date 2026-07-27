<?php

namespace App\MessageHandler;

use App\Entity\Message;
use App\Message\ChatbotQueryMessage;
use App\Repository\BoutiqueRepository;
use App\Repository\ChatbotConfigRepository;
use App\Repository\ConversationRepository;
use App\Repository\MessageRepository;
use App\Service\Chat\ChatbotService;
use App\Service\Chat\MercurePublisher;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class ChatbotQueryMessageHandler
{
    public function __construct(
        private ChatbotService $chatbotService,
        private ChatbotConfigRepository $configRepository,
        private ConversationRepository $conversationRepository,
        private MessageRepository $messageRepository,
        private BoutiqueRepository $boutiqueRepository,
        private MercurePublisher $mercurePublisher,
    ) {
    }

    public function __invoke(ChatbotQueryMessage $query): void
    {
        $boutique = $this->boutiqueRepository->find($query->getBoutiqueId());
        if (null === $boutique) {
            return;
        }

        $config = $this->configRepository->findEnabledByBoutique($boutique);
        if (null === $config) {
            return;
        }

        $conversation = $this->conversationRepository->find($query->getConversationId());
        if (null === $conversation) {
            return;
        }

        $userMessage = $this->messageRepository->find($query->getUserMessageId());
        if (!$userMessage instanceof Message || $userMessage->getConversation() !== $conversation) {
            return;
        }

        // A Redis retry can happen after the bot row was persisted but before Mercure returned.
        // Republish the existing response instead of generating and storing a duplicate.
        $existingResponse = $this->messageRepository->findBotResponseAfter($userMessage);
        if ($existingResponse instanceof Message) {
            $this->mercurePublisher->publishMessage($existingResponse);

            return;
        }

        $conversationId = (string) $conversation->getId();
        $this->mercurePublisher->publishTyping($conversationId, 'bot');

        $history = $this->messageRepository->findBy(
            ['conversation' => $conversation],
            ['createdAt' => 'ASC'],
        );

        $historyData = [];
        foreach ($history as $msg) {
            $historyData[] = [
                'senderType' => $msg->getSenderType(),
                'content' => $msg->getContent(),
            ];
        }

        $response = $this->chatbotService->generateResponse(
            $config,
            $query->getUserMessage(),
            $historyData,
        );

        if ('' === $response) {
            return;
        }

        $botMessage = new Message($conversation, 'bot', $response);
        $this->messageRepository->save($botMessage, true);

        $conversation->touch();
        $this->conversationRepository->save($conversation, true);

        $this->mercurePublisher->publishMessage($botMessage);
    }
}
