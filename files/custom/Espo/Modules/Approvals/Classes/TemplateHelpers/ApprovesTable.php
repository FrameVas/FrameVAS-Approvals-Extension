<?php

namespace Espo\Modules\Approvals\Classes\TemplateHelpers;

use Espo\Core\Htmlizer\Helper;
use Espo\Core\Htmlizer\Helper\Data;
use Espo\Core\Htmlizer\Helper\Result;
use Espo\Modules\Approvals\Tools\Approves\PdfTableBuilder;

/**
 * PDF template helper:
 *
 * {{approvesTable newApprovesList columns="stage,approver,title,status,date" hideEmpty="true"}}
 *
 * The first argument is the approver list ({fieldName}List from the PDF data), or the field name
 * in quotes: {{approvesTable "newApproves" columns="..."}}.
 * See PdfTableBuilder for column keys.
 */
class ApprovesTable implements Helper
{
    public function __construct(
        private PdfTableBuilder $tableBuilder,
    ) {}

    public function render(Data $data): Result
    {
        $argument = $data->getArgumentList()[0] ?? null;

        if (is_string($argument)) {
            $root = $data->getRootContext();
            $argument = $root[$argument . 'List'] ?? $root[$argument] ?? null;
        }

        if (!is_array($argument) || !$argument) {
            return Result::createEmpty();
        }

        $items = array_values(array_filter($argument, 'is_array'));

        $columns = $data->hasOption('columns') ? (string) $data->getOption('columns') : null;

        if ($columns === null || trim($columns) === '') {
            $isStages = (bool) array_filter($items, fn ($item) => !empty($item['stageLabel']));
            $columns = $this->tableBuilder->getDefaultColumns($isStages);
        }

        $hideEmpty = !in_array(
            strtolower((string) ($data->hasOption('hideEmpty') ? $data->getOption('hideEmpty') : 'true')),
            ['false', '0', 'no'],
            true
        );

        return Result::createSafeString($this->tableBuilder->build($items, $columns, $hideEmpty));
    }
}
