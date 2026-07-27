<?php

namespace App\Message;

final class ChatbotQueryMessage
{
    public function __construct(
        private readonly string $conversationId,
        private readonly string $boutiqueId,
        private readonly string $userMessage,
        private readonly string $userMessageId,
    ) {
    }

    public function getConversationId(): string
    {
        return $this->conversationId;
    }

    public function getBoutiqueId(): string
    {
        return $this->boutiqueId;
    }

    public function getUserMessage(): string
    {
        return $this->userMessage;
    }

    public function getUserMessageId(): string
    {
        return $this->userMessageId;
    }
}
