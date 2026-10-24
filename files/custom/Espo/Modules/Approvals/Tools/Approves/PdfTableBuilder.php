<?php

namespace Espo\Modules\Approvals\Tools\Approves;

use Espo\Core\Utils\DateTime\DateTimeFactory;
use Espo\Core\Utils\Language;
use Espo\Core\Utils\Metadata;
use Espo\Entities\User;
use Espo\ORM\EntityManager;
use Throwable;

/**
 * Builds the approvers table for PDF templates.
 *
 * Column keys:
 *   number     row number
 *   stage      stage number
 *   approver   user name
 *   title      user's Title field
 *   status     decision (translated)
 *   date       decision date
 *   comment    decision comment
 *   signature  an empty cell to sign in
 *   any other  a User field, e.g. phoneNumber, emailAddress, defaultTeam, cDepartment
 *
 * A key can have a custom header: "approver:Full name".
 */
class PdfTableBuilder
{
    private const BUILT_IN = ['number', 'stage', 'approver', 'title', 'status', 'date', 'comment', 'signature'];

    // Never hidden as empty: they are the core of the table, or empty by design.
    private const ALWAYS_SHOWN = ['number', 'approver', 'status', 'signature'];

    private const CELL_STYLE = 'border: 1px solid #999; padding: 4px 6px; text-align: left; vertical-align: top;';

    public function __construct(
        private Language $language,
        private Metadata $metadata,
        private EntityManager $entityManager,
        private DateTimeFactory $dateTimeFactory,
        private User $user,
    ) {}

    /**
     * The default columns: stage (in stages mode), approver, title, status, date, comment.
     */
    public function getDefaultColumns(bool $isStages): string
    {
        return ($isStages ? 'stage,' : '') . 'approver,title,status,date,comment';
    }

    /**
     * @param array<int, array<string, mixed>> $items Items as prepared by the PDF data loader.
     * @param string $columns Comma-separated column keys, optionally with headers: "stage,approver:Name".
     * @param bool $hideEmpty Hide columns where all cells are empty.
     */
    public function build(array $items, string $columns, bool $hideEmpty = true): string
    {
        if (!$items) {
            return '';
        }

        $columnList = $this->parseColumns($columns);

        if (!$columnList) {
            return '';
        }

        $userValues = $this->loadUserValues($items, $columnList);

        $rows = [];

        foreach ($items as $i => $item) {
            $row = [];

            foreach ($columnList as $column) {
                $row[] = $this->getCellValue($column['key'], $item, $i, $userValues);
            }

            $rows[] = $row;
        }

        // Hide columns where every cell is empty (e.g. Title when no approver has one).
        $visible = [];

        foreach ($columnList as $c => $column) {
            $isEmpty = !array_filter($rows, fn ($row) => $row[$c] !== '');

            $isHideable = !in_array($column['key'], self::ALWAYS_SHOWN, true) &&
                ($hideEmpty || $column['key'] === 'stage');

            if ($isEmpty && $isHideable) {
                continue;
            }

            $visible[] = $c;
        }

        $html = '<table style="border-collapse: collapse; width: 100%;"><thead><tr>';

        foreach ($visible as $c) {
            $html .= '<th style="' . self::CELL_STYLE . ' background-color: #eee;">' .
                $this->escape($columnList[$c]['label']) . '</th>';
        }

        $html .= '</tr></thead><tbody>';

        foreach ($rows as $row) {
            $html .= '<tr>';

            foreach ($visible as $c) {
                $style = self::CELL_STYLE;

                if ($columnList[$c]['key'] === 'signature') {
                    $style .= ' min-width: 120px; height: 28px;';
                }

                $html .= '<td style="' . $style . '">' . nl2br($this->escape($row[$c])) . '</td>';
            }

            $html .= '</tr>';
        }

        return $html . '</tbody></table>';
    }

    /**
     * @return array<int, array{key: string, label: string}>
     */
    private function parseColumns(string $columns): array
    {
        $list = [];

        foreach (explode(',', $columns) as $part) {
            $part = trim($part);

            if ($part === '') {
                continue;
            }

            $key = $part;
            $label = null;

            if (str_contains($part, ':')) {
                [$key, $label] = array_map('trim', explode(':', $part, 2));
            }

            if (!in_array($key, self::BUILT_IN, true) && !$this->isUserField($key)) {
                // Unknown keys are skipped, so a typo doesn't break the PDF.
                continue;
            }

            $list[] = [
                'key' => $key,
                'label' => $label !== null && $label !== '' ? $label : $this->getDefaultLabel($key),
            ];
        }

        return $list;
    }

    private function isUserField(string $field): bool
    {
        return (bool) $this->metadata->get(['entityDefs', User::ENTITY_TYPE, 'fields', $field, 'type']);
    }

    private function getDefaultLabel(string $key): string
    {
        return match ($key) {
            'number' => '№',
            'stage' => $this->language->translateLabel('Stage'),
            'approver' => $this->language->translateLabel('Approver'),
            'status' => $this->language->translateLabel('Status'),
            'date' => $this->language->translateLabel('Decision Date'),
            'comment' => $this->language->translateLabel('Comment'),
            'signature' => $this->language->translateLabel('Signature'),
            default => $this->language->translateLabel($key, 'fields', User::ENTITY_TYPE),
        };
    }

    /**
     * @param array<string, mixed> $item
     * @param array<string, array<string, string>> $userValues
     */
    private function getCellValue(string $key, array $item, int $index, array $userValues): string
    {
        return match ($key) {
            'number' => (string) ($index + 1),
            'stage' => !empty($item['stageLabel']) ? (string) $item['stage'] : '',
            'approver' => (string) ($item['name'] ?? ''),
            'title' => (string) ($item['title'] ?? ''),
            'status' => (string) ($item['status'] ?? ''),
            'date' => (string) ($item['resolvedAt'] ?? ''),
            'comment' => (string) ($item['comment'] ?? ''),
            'signature' => '',
            default => $userValues[$item['id'] ?? ''][$key] ?? '',
        };
    }

    /**
     * Loads values of User fields used as columns, for all approvers in one query.
     *
     * @param array<int, array<string, mixed>> $items
     * @param array<int, array{key: string, label: string}> $columnList
     * @return array<string, array<string, string>> User ID => field => display value.
     */
    private function loadUserValues(array $items, array $columnList): array
    {
        $fields = array_values(array_filter(
            array_map(fn ($column) => $column['key'], $columnList),
            fn ($key) => !in_array($key, self::BUILT_IN, true)
        ));

        $ids = array_values(array_filter(array_map(fn ($item) => $item['id'] ?? null, $items)));

        if (!$fields || !$ids) {
            return [];
        }

        $users = $this->entityManager
            ->getRDBRepositoryByClass(User::class)
            ->where(['id' => $ids])
            ->find();

        $values = [];

        foreach ($users as $user) {
            foreach ($fields as $field) {
                $values[$user->getId()][$field] = $this->formatUserField($user, $field);
            }
        }

        return $values;
    }

    private function formatUserField(User $user, string $field): string
    {
        $type = $this->metadata->get(['entityDefs', User::ENTITY_TYPE, 'fields', $field, 'type']);

        try {
            switch ($type) {
                case 'link':
                case 'linkOne':
                case 'linkParent':
                    if (!$user->get($field . 'Name')) {
                        $user->loadLinkField($field);
                    }

                    return (string) $user->get($field . 'Name');

                case 'linkMultiple':
                    $user->loadLinkMultipleField($field);

                    return implode(', ', array_filter(array_map(
                        fn ($id) => $user->getLinkMultipleName($field, $id),
                        $user->getLinkMultipleIdList($field)
                    )));

                case 'enum':
                    $value = $user->get($field);

                    return $value === null || $value === '' ?
                        '' : (string) $this->language->translateOption($value, $field, User::ENTITY_TYPE);

                case 'multiEnum':
                case 'array':
                case 'checklist':
                    $value = $user->get($field) ?? [];

                    return implode(', ', array_map(
                        fn ($v) => (string) $this->language->translateOption($v, $field, User::ENTITY_TYPE),
                        is_array($value) ? $value : []
                    ));

                case 'bool':
                    return $user->get($field) ?
                        $this->language->translateLabel('Yes') :
                        $this->language->translateLabel('No');

                case 'date':
                    $value = $user->get($field);

                    return $value ? $this->dateTimeFactory->createWithUserTimeZone($this->user)
                        ->convertSystemDate($value) : '';

                case 'datetime':
                case 'datetimeOptional':
                    $value = $user->get($field);

                    return $value ? $this->dateTimeFactory->createWithUserTimeZone($this->user)
                        ->convertSystemDateTime($value) : '';
            }

            $value = $user->get($field);

            return is_scalar($value) ? trim((string) $value) : '';
        } catch (Throwable) {
            return '';
        }
    }

    private function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }
}
