<?php

namespace App\Repository;

use App\Entity\Message;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<Message> */
final class MessageRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Message::class);
    }

    public function save(Message $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function findBotResponseAfter(Message $userMessage): ?Message
    {
        return $this->createQueryBuilder('message')
            ->andWhere('message.conversation = :conversation')
            ->andWhere('message.senderType = :senderType')
            ->andWhere('message.createdAt > :createdAt')
            ->setParameter('conversation', $userMessage->getConversation())
            ->setParameter('senderType', 'bot')
            ->setParameter('createdAt', $userMessage->getCreatedAt())
            ->orderBy('message.createdAt', 'ASC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /** @return array<Message> */
    public function findUnreadByConversation(string $conversationId): array
    {
        return $this->createQueryBuilder('m')
            ->andWhere('m.conversation = :conversationId')
            ->andWhere('m.read = :read')
            ->setParameter('conversationId', $conversationId)
            ->setParameter('read', false)
            ->orderBy('m.createdAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function markAsRead(string $conversationId, string $senderType = 'admin'): void
    {
        $this->createQueryBuilder('m')
            ->update()
            ->set('m.read', ':read')
            ->set('m.readAt', ':now')
            ->andWhere('m.conversation = :conversationId')
            ->andWhere('m.senderType != :senderType')
            ->andWhere('m.read = :false')
            ->setParameter('read', true)
            ->setParameter('now', new \DateTimeImmutable())
            ->setParameter('conversationId', $conversationId)
            ->setParameter('senderType', $senderType)
            ->setParameter('false', false)
            ->getQuery()
            ->execute();
    }
}
