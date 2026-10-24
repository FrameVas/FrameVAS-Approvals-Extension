<?php

namespace Espo\Modules\Approvals\Tools\Approves;

use Espo\Core\Acl;
use Espo\Core\Exceptions\BadRequest;
use Espo\Core\Exceptions\Error\Body;
use Espo\Core\Exceptions\Forbidden;
use Espo\Core\Exceptions\NotFound;
use Espo\Core\Field\DateTime;
use Espo\Entities\User;
use Espo\ORM\EntityManager;
use stdClass;

class ResolveService
{
    public const ACTION_APPROVE = 'approve';
    public const ACTION_DECLINE = 'decline';

    public function __construct(
        private EntityManager $entityManager,
        private Acl $acl,
        private User $user,
        private Util $util,
        private Notifier $notifier,
        private StreamLogger $streamLogger,
    ) {}

    /**
     * Records the current user's decision.
     *
     * @return stdClass The new field value and overall status.
     * @throws BadRequest
     * @throws Forbidden
     * @throws NotFound
     */
    public function resolve(
        string $entityType,
        string $id,
        string $field,
        string $action,
        ?string $comment
    ): stdClass {

        if (!in_array($action, [self::ACTION_APPROVE, self::ACTION_DECLINE], true)) {
            throw new BadRequest("Bad action.");
        }

        if (!$this->util->isApprovesField($entityType, $field)) {
            throw new BadRequest("Not an approves field.");
        }

        $entity = $this->entityManager->getEntityById($entityType, $id);

        if (!$entity) {
            throw new NotFound();
        }

        // Approvers only need read access to the record.
        if (!$this->acl->checkEntityRead($entity)) {
            throw new Forbidden("No read access.");
        }

        $list = $this->util->normalizeForField($entityType, $field, $entity->get($field));
        $activeIds = $this->util->getActiveIds($list, $this->util->getMode($entityType, $field));

        if (!in_array($this->user->getId(), $activeIds, true)) {
            throw Forbidden::createWithBody(
                'Not allowed to resolve.',
                Body::create()->withMessageTranslation('approvesNotAllowed')
            );
        }

        if ($this->util->isCommentDisabled($entityType, $field, $action)) {
            $comment = null;
        }

        $status = $action === self::ACTION_APPROVE ? Util::STATUS_APPROVED : Util::STATUS_DECLINED;

        foreach ($list as $item) {
            if ($item->id !== $this->user->getId()) {
                continue;
            }

            $item->status = $status;
            $item->resolvedAt = DateTime::createNow()->toString();
            $item->comment = $this->util->cleanComment($comment);
        }

        $entity->set($field, $list);

        $this->entityManager->saveEntity($entity, [Util::OPTION_RESOLVE => true]);

        $this->notifier->notifyDecision($entity, $status, $this->user);

        foreach ($this->util->normalizeForField($entityType, $field, $entity->get($field)) as $item) {
            if ($item->id === $this->user->getId()) {
                $this->streamLogger->logDecision($entity, $field, $item);
            }
        }

        return (object) [
            $field => $entity->get($field),
            Util::getStatusAttribute($field) => $entity->get(Util::getStatusAttribute($field)),
        ];
    }
}
