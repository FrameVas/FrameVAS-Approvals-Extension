<?php

namespace Espo\Modules\Approvals\Hooks\Common;

use Espo\Core\Hook\Hook\AfterSave;
use Espo\Core\Hook\Hook\BeforeSave;
use Espo\Core\ORM\Repository\Option\SaveOption;
use Espo\Core\Name\Field;
use Espo\Entities\User;
use Espo\Modules\Approvals\Tools\Approves\Notifier;
use Espo\Modules\Approvals\Tools\Approves\StreamLogger;
use Espo\Modules\Approvals\Tools\Approves\Util;
use Espo\ORM\Entity;
use Espo\ORM\EntityManager;
use Espo\ORM\Repository\Option\SaveOptions;
use stdClass;

/**
 * Applies to every entity type that has a field of the 'approves' type.
 *
 * @implements BeforeSave<Entity>
 * @implements AfterSave<Entity>
 */
class Approves implements BeforeSave, AfterSave
{
    // Run after the before-save formula (11), so formula changes are processed too.
    public static int $order = 20;

    public function __construct(
        private Util $util,
        private Notifier $notifier,
        private StreamLogger $streamLogger,
        private EntityManager $entityManager,
        private User $user,
    ) {}

    public function beforeSave(Entity $entity, SaveOptions $options): void
    {
        foreach ($this->util->getFieldList($entity->getEntityType()) as $field) {
            if (!$entity->isNew() && !$entity->isAttributeChanged($field)) {
                continue;
            }

            $entityType = $entity->getEntityType();

            $list = $this->util->normalizeForField($entityType, $field, $entity->get($field));

            // A user editing the record (UI or API) can add or remove approvers,
            // but can't set decisions. Decisions are kept from the saved value.
            $isUserEdit = $options->get(SaveOption::API) && !$options->get(Util::OPTION_RESOLVE);

            if ($isUserEdit) {
                $previous = $entity->isNew() ?
                    [] :
                    $this->util->normalizeForField($entityType, $field, $entity->getFetched($field));

                // Dropped users may leave an empty stage; normalizing renumbers stages.
                $list = $this->util->normalize($this->protectDecisions($list, $previous));
            }

            $entity->set($field, $list);
            $entity->set(Util::getStatusAttribute($field), $this->util->computeStatus($list));
            $entity->set(
                Util::getIndexAttribute($field),
                $this->util->buildIndex($list, $this->util->getMode($entityType, $field))
            );
        }
    }

    public function afterSave(Entity $entity, SaveOptions $options): void
    {
        if ($options->get(SaveOption::SILENT)) {
            return;
        }

        $entityType = $entity->getEntityType();

        foreach ($this->util->getFieldList($entityType) as $field) {
            if (!$entity->isNew() && !$entity->isAttributeChanged($field)) {
                continue;
            }

            $mode = $this->util->getMode($entityType, $field);

            $previous = $entity->isNew() ?
                [] :
                $this->util->normalizeForField($entityType, $field, $entity->getFetched($field));

            $current = $this->util->normalizeForField($entityType, $field, $entity->get($field));

            // Decisions are logged by the resolve service, with the comment.
            if (!$options->get(Util::OPTION_RESOLVE) && !$options->get(SaveOption::SKIP_AUDITED)) {
                $this->streamLogger->logChanges($entity, $field, $previous, $current);
            }

            if ($options->get(SaveOption::NO_NOTIFICATIONS)) {
                continue;
            }

            $previousActiveIds = $this->util->getActiveIds($previous, $mode);
            $activeIds = $this->util->getActiveIds($current, $mode);

            // Notify only users whose turn has just started.
            foreach (array_diff($activeIds, $previousActiveIds) as $userId) {
                $this->notifier->notifyApprover($entity, $userId, $this->user);
            }
        }
    }

    /**
     * @param stdClass[] $list
     * @param stdClass[] $previous
     * @return stdClass[]
     */
    private function protectDecisions(array $list, array $previous): array
    {
        $previousMap = [];

        foreach ($previous as $item) {
            $previousMap[$item->id] = $item;
        }

        $names = $this->getActiveUserNames(array_values(array_filter(array_merge(
            array_map(fn ($item) => $item->id, $list),
            array_map(fn ($item) => $item->deputyId ?? null, $list)
        ))));

        $result = [];

        foreach ($list as $item) {
            $old = $previousMap[$item->id] ?? null;

            if ($old) {
                // An existing approver: keep the saved decision and who made it.
                $item->status = $old->status;
                $item->resolvedAt = $old->resolvedAt;
                $item->resolvedById = $old->resolvedById ?? null;
                $item->resolvedByName = $old->resolvedByName ?? null;
                $item->comment = $old->comment;
            } else {
                // A new approver: must be an existing active user, and starts as pending.
                if (!array_key_exists($item->id, $names)) {
                    continue;
                }

                $item->status = Util::STATUS_PENDING;
                $item->resolvedAt = null;
                $item->resolvedById = null;
                $item->resolvedByName = null;
                $item->comment = null;
            }

            $item->name = $names[$item->id] ?? $old?->name ?? $item->name;

            // A deputy can be added, changed or removed at any time; it must be an active user.
            if (($item->deputyId ?? null) && array_key_exists($item->deputyId, $names)) {
                $item->deputyName = $names[$item->deputyId];
            } else {
                $item->deputyId = null;
                $item->deputyName = null;
            }

            $result[] = $item;
        }

        return $result;
    }

    /**
     * @param string[] $ids
     * @return array<string, string>
     */
    private function getActiveUserNames(array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        $users = $this->entityManager
            ->getRDBRepositoryByClass(User::class)
            ->select([Field::ID, Field::NAME])
            ->where([
                Field::ID => $ids,
                'isActive' => true,
                'type!=' => [User::TYPE_PORTAL, User::TYPE_API, User::TYPE_SYSTEM],
            ])
            ->find();

        $names = [];

        foreach ($users as $user) {
            $names[$user->getId()] = (string) $user->get(Field::NAME);
        }

        return $names;
    }
}
