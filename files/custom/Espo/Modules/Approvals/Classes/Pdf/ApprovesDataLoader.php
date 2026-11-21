<?php

namespace Espo\Modules\Approvals\Classes\Pdf;

use Espo\Core\Utils\DateTime\DateTimeFactory;
use Espo\Core\Utils\Language;
use Espo\Entities\User;
use Espo\Modules\Approvals\Tools\Approves\PdfTableBuilder;
use Espo\Modules\Approvals\Tools\Approves\Util;
use Espo\ORM\Entity;
use Espo\ORM\EntityManager;
use Espo\Tools\Pdf\Data\DataLoader;
use Espo\Tools\Pdf\Params;
use stdClass;
use Throwable;

/**
 * Adds readable approver data to PDF templates, for every Approves field of the entity:
 *
 * {{field}}                    Text: "Name (Status), Name (Status)".
 * {{{fieldTable}}}             A ready-made HTML table.
 * {{#each fieldList}}...{{/each}}  A loop. Item keys: name, title, deputy, decidedBy, isDecidedByDeputy,
 *                              status, statusRaw, stage, stageLabel,
 *                              resolvedAt, comment, isApproved, isDeclined, isPending, isWaiting.
 *
 * Columns that are empty for everyone (e.g. Title) are hidden.
 * For a table with chosen columns, use the approvesTable helper (see ApprovesTable).
 *
 * Texts are in the language of the user who prints, dates in their time zone.
 */
class ApprovesDataLoader implements DataLoader
{
    public function __construct(
        private Util $util,
        private Language $language,
        private DateTimeFactory $dateTimeFactory,
        private User $user,
        private EntityManager $entityManager,
        private PdfTableBuilder $tableBuilder,
    ) {}

    public function load(Entity $entity, Params $params): stdClass
    {
        $data = new stdClass();
        $entityType = $entity->getEntityType();

        $fieldLists = [];
        $userIds = [];

        foreach ($this->util->getFieldList($entityType) as $field) {
            $fieldLists[$field] = $this->util->normalizeForField($entityType, $field, $entity->get($field));

            foreach ($fieldLists[$field] as $item) {
                $userIds[] = $item->id;
            }
        }

        $titles = $this->getUserTitles(array_values(array_unique($userIds)));

        foreach ($fieldLists as $field => $list) {
            $isStages = $this->util->getMode($entityType, $field) === Util::MODE_STAGES;
            $activeIds = $this->util->getActiveIds($list, $this->util->getMode($entityType, $field));
            $isPendingOverall = $this->util->computeStatus($list) === Util::STATUS_PENDING;

            $items = [];

            foreach ($list as $item) {
                $isWaiting = $isStages && $isPendingOverall &&
                    $item->status === Util::STATUS_PENDING && !in_array($item->id, $activeIds, true);

                $isByDeputy = ($item->resolvedById ?? null) && $item->resolvedById !== $item->id;

                $status = $isWaiting ?
                    $this->language->translateLabel('Waiting') :
                    $this->translateStatus($item->status);

                if ($isByDeputy && $item->resolvedByName) {
                    $status .= ' — ' . $item->resolvedByName;
                }

                $items[] = [
                    'id' => $item->id,
                    'name' => (string) $item->name,
                    'deputy' => (string) ($item->deputyName ?? ''),
                    'decidedBy' => (string) ($item->resolvedByName ?? ''),
                    'isDecidedByDeputy' => $isByDeputy,
                    'title' => $titles[$item->id] ?? '',
                    'status' => $status,
                    'statusRaw' => $item->status,
                    'stage' => $item->stage,
                    'stageLabel' => $isStages ? $this->stageLabel($item->stage) : '',
                    'resolvedAt' => $this->formatDateTime($item->resolvedAt),
                    'comment' => (string) ($item->comment ?? ''),
                    'isApproved' => $item->status === Util::STATUS_APPROVED,
                    'isDeclined' => $item->status === Util::STATUS_DECLINED,
                    'isPending' => $item->status === Util::STATUS_PENDING,
                    'isWaiting' => $isWaiting,
                ];
            }

            $data->$field = implode(', ', array_map(
                fn ($item) => $item['name'] . ' (' . $item['status'] . ')',
                $items
            ));

            $data->{$field . 'List'} = $items;
            $data->{$field . 'Table'} = $this->tableBuilder->build(
                $items,
                $this->tableBuilder->getDefaultColumns($isStages)
            );
        }

        return $data;
    }

    /**
     * Users' Title field values, loaded in one query.
     *
     * @param string[] $ids
     * @return array<string, string>
     */
    private function getUserTitles(array $ids): array
    {
        if (!$ids) {
            return [];
        }

        $users = $this->entityManager
            ->getRDBRepositoryByClass(User::class)
            ->select(['id', 'title'])
            ->where(['id' => $ids])
            ->find();

        $titles = [];

        foreach ($users as $user) {
            $titles[$user->getId()] = trim((string) $user->get('title'));
        }

        return $titles;
    }

    private function translateStatus(string $status): string
    {
        $map = $this->language->get(['Global', 'options', 'approvesStatus']);

        return is_array($map) && isset($map[$status]) ? $map[$status] : $status;
    }

    private function stageLabel(int $stage): string
    {
        return str_replace('{number}', (string) $stage, $this->language->translateLabel('approvesStage', 'messages'));
    }

    private function formatDateTime(?string $value): string
    {
        if (!$value) {
            return '';
        }

        try {
            return $this->dateTimeFactory->createWithUserTimeZone($this->user)->convertSystemDateTime($value);
        } catch (Throwable) {
            return $value;
        }
    }
}
