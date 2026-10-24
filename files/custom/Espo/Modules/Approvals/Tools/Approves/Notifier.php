<?php

namespace Espo\Modules\Approvals\Tools\Approves;

use Espo\Core\Name\Field;
use Espo\Core\Utils\Config;
use Espo\Core\Utils\Language;
use Espo\Core\Utils\Language\LanguageFactory;
use Espo\Entities\Preferences;
use Espo\Entities\Notification;
use Espo\Entities\User;
use Espo\ORM\Entity;
use Espo\ORM\EntityManager;

class Notifier
{
    /** @var array<string, Language> */
    private array $languages = [];

    public function __construct(
        private EntityManager $entityManager,
        private LanguageFactory $languageFactory,
        private Config $config,
    ) {}

    /**
     * Tells a user that their approval is needed.
     */
    public function notifyApprover(Entity $entity, string $userId, ?User $actor): void
    {
        $this->send($entity, $userId, $actor, 'approvesRequested', 'text-warning');
    }

    /**
     * Tells the record owner (assigned user, or creator) about a decision.
     */
    public function notifyDecision(Entity $entity, string $status, User $actor): void
    {
        $recipientId = null;

        if ($entity->hasAttribute('assignedUserId')) {
            $recipientId = $entity->get('assignedUserId');
        }

        if (!$recipientId && $entity->hasAttribute(Field::CREATED_BY . 'Id')) {
            $recipientId = $entity->get(Field::CREATED_BY . 'Id');
        }

        if (!is_string($recipientId) || $recipientId === $actor->getId()) {
            return;
        }

        $isApproved = $status === Util::STATUS_APPROVED;

        $this->send(
            $entity,
            $recipientId,
            $actor,
            $isApproved ? 'approvesApproved' : 'approvesDeclined',
            $isApproved ? 'text-success' : 'text-danger'
        );
    }

    private function send(Entity $entity, string $userId, ?User $actor, string $messageKey, string $style): void
    {
        if ($actor && $actor->getId() === $userId) {
            return;
        }

        /** @var ?User $recipient */
        $recipient = $this->entityManager->getEntityById(User::ENTITY_TYPE, $userId);

        if (!$recipient || !$recipient->isActive()) {
            return;
        }

        $notification = $this->entityManager->getRDBRepositoryByClass(Notification::class)->getNew();

        $notification
            ->setType(Notification::TYPE_MESSAGE)
            ->setUserId($userId)
            ->setMessage($this->getUserLanguage($userId)->translateLabel($messageKey, 'messages'))
            ->setRelated($entity)
            ->setData([
                'entityType' => $entity->getEntityType(),
                'entityId' => $entity->getId(),
                'entityName' => $entity->get(Field::NAME) ?? $entity->getId(),
                'userId' => $actor?->getId(),
                'userName' => $actor?->get(Field::NAME),
                'style' => $style,
            ]);

        $this->entityManager->saveEntity($notification);
    }

    /**
     * The language the recipient uses (from their preferences), or the system default.
     */
    private function getUserLanguage(string $userId): Language
    {
        /** @var ?Preferences $preferences */
        $preferences = $this->entityManager->getEntityById(Preferences::ENTITY_TYPE, $userId);

        $code = $preferences?->getLanguage() ?: $this->config->get('language') ?: 'en_US';

        if (!isset($this->languages[$code])) {
            $this->languages[$code] = $this->languageFactory->create($code);
        }

        return $this->languages[$code];
    }
}
