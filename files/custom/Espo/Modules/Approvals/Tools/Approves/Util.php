<?php

namespace Espo\Modules\Approvals\Tools\Approves;

use Espo\Core\Utils\Metadata;
use stdClass;

class Util
{
    public const FIELD_TYPE = 'approves';

    public const STATUS_PENDING = 'Pending';
    public const STATUS_APPROVED = 'Approved';
    public const STATUS_DECLINED = 'Declined';

    public const MODE_PARALLEL = 'parallel';
    public const MODE_STAGES = 'stages';

    /** Save option set when a decision is recorded through the resolve endpoint. */
    public const OPTION_RESOLVE = 'approvesResolve';

    private const COMMENT_MAX_LENGTH = 2000;

    /** @var array<string, string[]> */
    private array $fieldListCache = [];

    public function __construct(
        private Metadata $metadata,
    ) {}

    /**
     * Names of all fields of the 'approves' type in an entity type.
     *
     * @return string[]
     */
    public function getFieldList(string $entityType): array
    {
        if (!array_key_exists($entityType, $this->fieldListCache)) {
            $list = [];

            /** @var array<string, array<string, mixed>> $fieldDefs */
            $fieldDefs = $this->metadata->get(['entityDefs', $entityType, 'fields']) ?? [];

            foreach ($fieldDefs as $field => $defs) {
                if (($defs['type'] ?? null) === self::FIELD_TYPE) {
                    $list[] = $field;
                }
            }

            $this->fieldListCache[$entityType] = $list;
        }

        return $this->fieldListCache[$entityType];
    }

    public function isApprovesField(string $entityType, string $field): bool
    {
        return in_array($field, $this->getFieldList($entityType), true);
    }

    /**
     * 'parallel' or 'stages'. Fields from versions before 1.3 with 'Sequential' checked work as stages.
     */
    public function getMode(string $entityType, string $field): string
    {
        $mode = $this->metadata->get(['entityDefs', $entityType, 'fields', $field, 'approvalMode']);

        if ($mode === self::MODE_STAGES || $mode === self::MODE_PARALLEL) {
            return $mode;
        }

        return $this->isLegacySequential($entityType, $field) ? self::MODE_STAGES : self::MODE_PARALLEL;
    }

    /**
     * The 'Sequential' option from versions before 1.3. Items saved then have no stage number;
     * in a sequential field each of them is its own stage, in the listed order.
     */
    private function isLegacySequential(string $entityType, string $field): bool
    {
        return (bool) $this->metadata->get(['entityDefs', $entityType, 'fields', $field, 'sequential']);
    }

    /**
     * Normalizes a value of a specific field.
     *
     * @return stdClass[]
     */
    public function normalizeForField(string $entityType, string $field, mixed $value): array
    {
        return $this->normalize(
            $value,
            $this->isLegacySequential($entityType, $field),
            $this->areDeputiesAllowed($entityType, $field)
        );
    }

    /**
     * The 'Allow Deputies' option. When off, deputies are ignored everywhere (they can't act,
     * aren't notified or shown), so turning the option off later is safe.
     */
    public function areDeputiesAllowed(string $entityType, string $field): bool
    {
        return (bool) $this->metadata->get(['entityDefs', $entityType, 'fields', $field, 'deputiesAllowed']);
    }

    /**
     * The 'Audited' option. It has its own name, because EspoCRM's standard 'audited'
     * would log the raw JSON value in the Stream.
     */
    public function isAudited(string $entityType, string $field): bool
    {
        return (bool) $this->metadata->get(['entityDefs', $entityType, 'fields', $field, 'approvalsAudited']);
    }

    /**
     * Whether the comment is hidden for an action ('approve' or 'decline').
     */
    public function isCommentDisabled(string $entityType, string $field, string $action): bool
    {
        return (bool) $this->metadata->get(['entityDefs', $entityType, 'fields', $field, $action . 'CommentDisabled']);
    }

    public static function getStatusAttribute(string $field): string
    {
        return $field . 'Status';
    }

    public static function getIndexAttribute(string $field): string
    {
        return $field . 'Index';
    }

    /**
     * A hidden text used by the search filter. One token per approver:
     * |{userId}:{status}:{A or W}| where A means the user can act now (it's their turn), W - not.
     * Filters search it with LIKE, e.g. '%|abc123:Pending:A|%' finds records waiting for user abc123.
     *
     * @param stdClass[] $list
     */
    public function buildIndex(array $list, string $mode): string
    {
        $activeItemIds = array_map(fn ($item) => $item->id, $this->getActiveItems($list, $mode));

        $index = '';

        foreach ($list as $item) {
            $flag = in_array($item->id, $activeItemIds, true) ? 'A' : 'W';

            // A deputy is indexed like the approver, so filters treat them as an approver of this item.
            foreach (array_filter([$item->id, $item->deputyId ?? null]) as $userId) {
                $index .= '|' . $userId . ':' . $item->status . ':' . $flag . '|';
            }
        }

        return $index;
    }

    /**
     * Converts a raw value into a clean list of approver items.
     * Items without a user ID and duplicates are dropped.
     *
     * @return stdClass[]
     */
    public function normalize(mixed $value, bool $legacySequential = false, bool $allowDeputies = true): array
    {
        if (!is_array($value)) {
            return [];
        }

        $list = [];
        $ids = [];

        foreach ($value as $raw) {
            $raw = is_array($raw) ? (object) $raw : $raw;

            if (!$raw instanceof stdClass) {
                continue;
            }

            $id = $raw->id ?? null;

            if (!is_string($id) || $id === '' || in_array($id, $ids, true)) {
                continue;
            }

            $ids[] = $id;

            $status = $raw->status ?? null;

            if (!in_array($status, [self::STATUS_PENDING, self::STATUS_APPROVED, self::STATUS_DECLINED], true)) {
                $status = self::STATUS_PENDING;
            }

            $stage = $raw->stage ?? null;

            if (!is_int($stage) || $stage < 1) {
                $stage = is_numeric($stage) && (int) $stage >= 1 ?
                    (int) $stage :
                    ($legacySequential ? count($list) + 1 : 1);
            }

            $deputyId = $allowDeputies && is_string($raw->deputyId ?? null) && $raw->deputyId !== '' &&
                $raw->deputyId !== $id ? $raw->deputyId : null;

            $isResolved = $status !== self::STATUS_PENDING;

            // Who actually decided: the approver or the deputy. Unknown for decisions made before 1.1.
            $resolvedById = $isResolved && is_string($raw->resolvedById ?? null) && $raw->resolvedById !== '' ?
                $raw->resolvedById : null;

            $list[] = (object) [
                'id' => $id,
                'stage' => $stage,
                'name' => is_string($raw->name ?? null) ? $raw->name : null,
                'deputyId' => $deputyId,
                'deputyName' => $deputyId && is_string($raw->deputyName ?? null) ? $raw->deputyName : null,
                'status' => $status,
                'resolvedAt' => is_string($raw->resolvedAt ?? null) ? $raw->resolvedAt : null,
                'resolvedById' => $resolvedById,
                'resolvedByName' => $resolvedById && is_string($raw->resolvedByName ?? null) ?
                    $raw->resolvedByName : null,
                'comment' => $this->cleanComment($raw->comment ?? null),
            ];
        }

        return $this->compactStages($list);
    }

    /**
     * Sorts items by stage (keeping the order inside a stage) and renumbers stages as 1, 2, 3...
     *
     * @param stdClass[] $list
     * @return stdClass[]
     */
    private function compactStages(array $list): array
    {
        $positions = array_flip(array_keys($list));

        uksort($list, function ($a, $b) use ($list, $positions) {
            return [$list[$a]->stage, $positions[$a]] <=> [$list[$b]->stage, $positions[$b]];
        });

        $list = array_values($list);

        $map = [];

        foreach ($list as $item) {
            $map[$item->stage] ??= count($map) + 1;

            $item->stage = $map[$item->stage];
        }

        return $list;
    }

    public function cleanComment(mixed $comment): ?string
    {
        if (!is_string($comment)) {
            return null;
        }

        $comment = trim($comment);

        if ($comment === '') {
            return null;
        }

        return mb_substr($comment, 0, self::COMMENT_MAX_LENGTH);
    }

    /**
     * Overall status: '' (no approvers), Pending, Approved or Declined.
     *
     * @param stdClass[] $list
     */
    public function computeStatus(array $list): string
    {
        if ($list === []) {
            return '';
        }

        $statuses = array_map(fn ($item) => $item->status, $list);

        if (in_array(self::STATUS_DECLINED, $statuses, true)) {
            return self::STATUS_DECLINED;
        }

        if (in_array(self::STATUS_PENDING, $statuses, true)) {
            return self::STATUS_PENDING;
        }

        return self::STATUS_APPROVED;
    }

    /**
     * IDs of users who can approve or decline right now: approvers and their deputies.
     * Nobody can act once someone has declined.
     * In stages mode only pending approvers of the current stage can act. The current stage is
     * the first one with pending approvers: all earlier stages are fully approved.
     *
     * @param stdClass[] $list
     * @return string[]
     */
    public function getActiveIds(array $list, string $mode): array
    {
        if ($this->computeStatus($list) !== self::STATUS_PENDING) {
            return [];
        }

        $ids = [];

        foreach ($this->getActiveItems($list, $mode) as $item) {
            $ids[] = $item->id;

            if ($item->deputyId ?? null) {
                $ids[] = $item->deputyId;
            }
        }

        return array_values(array_unique($ids));
    }

    /**
     * Approver items that can be decided right now: pending, and in Stages mode in the current stage.
     * Each can be decided by its approver or by its deputy.
     *
     * @param stdClass[] $list
     * @return stdClass[]
     */
    public function getActiveItems(array $list, string $mode): array
    {
        if ($this->computeStatus($list) !== self::STATUS_PENDING) {
            return [];
        }

        $pending = array_filter($list, fn ($item) => $item->status === self::STATUS_PENDING);

        if ($mode === self::MODE_STAGES) {
            $currentStage = min(array_map(fn ($item) => $item->stage, $pending));

            $pending = array_filter($pending, fn ($item) => $item->stage === $currentStage);
        }

        return array_values($pending);
    }
}
