<?php

namespace Espo\Modules\Approvals\Tools\Approves;

use Espo\Core\Field\LinkParent;
use Espo\Core\Utils\Metadata;
use Espo\Entities\Note;
use Espo\ORM\Entity;
use Espo\ORM\EntityManager;
use stdClass;

/**
 * Writes readable Stream entries for fields with the 'Audited' option.
 */
class StreamLogger
{
    /** The Stream entry type; shown by approvals:views/stream/notes/approval. */
    public const NOTE_TYPE = 'Approval';

    public const ACTION_APPROVE = 'approve';
    public const ACTION_DECLINE = 'decline';
    public const ACTION_UPDATE = 'update';

    public function __construct(
        private EntityManager $entityManager,
        private Metadata $metadata,
        private Util $util,
    ) {}

    public function isEnabled(string $entityType, string $field): bool
    {
        return $this->util->isAudited($entityType, $field) &&
            $this->metadata->get(['scopes', $entityType, 'stream']);
    }

    /**
     * Logs an approver's decision.
     */
    public function logDecision(Entity $entity, string $field, stdClass $item): void
    {
        if (!$this->isEnabled($entity->getEntityType(), $field)) {
            return;
        }

        $isStages = $this->util->getMode($entity->getEntityType(), $field) === Util::MODE_STAGES;

        $this->save($entity, [
            'field' => $field,
            'action' => $item->status === Util::STATUS_APPROVED ? self::ACTION_APPROVE : self::ACTION_DECLINE,
            'stage' => $isStages ? $item->stage : null,
            'comment' => $item->comment,
        ]);
    }

    /**
     * Logs added, removed and moved approvers.
     *
     * @param stdClass[] $previous
     * @param stdClass[] $current
     */
    public function logChanges(Entity $entity, string $field, array $previous, array $current): void
    {
        if (!$this->isEnabled($entity->getEntityType(), $field)) {
            return;
        }

        $isStages = $this->util->getMode($entity->getEntityType(), $field) === Util::MODE_STAGES;

        $previousMap = [];

        foreach ($previous as $item) {
            $previousMap[$item->id] = $item;
        }

        $currentIds = array_map(fn ($item) => $item->id, $current);

        $added = [];
        $moved = [];

        foreach ($current as $item) {
            $old = $previousMap[$item->id] ?? null;

            if (!$old) {
                $added[] = $this->describe($item, $isStages);

                continue;
            }

            if ($isStages && $old->stage !== $item->stage) {
                $moved[] = [
                    'id' => $item->id,
                    'name' => $item->name,
                    'from' => $old->stage,
                    'to' => $item->stage,
                ];
            }
        }

        $removed = [];

        foreach ($previous as $item) {
            if (!in_array($item->id, $currentIds, true)) {
                $removed[] = $this->describe($item, $isStages);
            }
        }

        if (!$added && !$removed && !$moved) {
            return;
        }

        $this->save($entity, [
            'field' => $field,
            'action' => self::ACTION_UPDATE,
            'added' => $added,
            'removed' => $removed,
            'moved' => $moved,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function describe(stdClass $item, bool $isStages): array
    {
        return [
            'id' => $item->id,
            'name' => $item->name,
            'stage' => $isStages ? $item->stage : null,
        ];
    }

    /**
     * @param array<string, mixed> $data
     */
    private function save(Entity $entity, array $data): void
    {
        $note = $this->entityManager->getRDBRepositoryByClass(Note::class)->getNew();

        $note->setType(self::NOTE_TYPE);
        $note->setParent(LinkParent::create($entity->getEntityType(), $entity->getId()));
        $note->setData($data);

        $this->entityManager->saveEntity($note);
    }
}
