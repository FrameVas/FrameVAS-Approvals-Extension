<?php

namespace Espo\Modules\Approvals\Tools\Approves;

use Espo\Core\Utils\Metadata;
use Espo\Modules\Approvals\Classes\Pdf\ApprovesDataLoader;

/**
 * Registers the PDF data loader for entity types that have Approves fields,
 * so PDF templates get readable approver values.
 */
class PdfRegistrar
{
    public function __construct(
        private Metadata $metadata,
        private Util $util,
    ) {}

    /**
     * @return bool Whether metadata was changed (needs saving and a cache clear).
     */
    public function register(string $entityType): bool
    {
        /** @var string[] $list */
        $list = $this->metadata->get(['pdfDefs', $entityType, 'dataLoaderClassNameList']) ?? [];

        if (in_array(ApprovesDataLoader::class, $list, true)) {
            return false;
        }

        // The whole list is saved, so loaders already defined for the entity type are kept.
        $this->metadata->set('pdfDefs', $entityType, [
            'dataLoaderClassNameList' => array_values(array_merge($list, [ApprovesDataLoader::class])),
        ]);

        $this->metadata->save();

        return true;
    }

    public function registerAll(): bool
    {
        $changed = false;

        foreach (array_keys($this->metadata->get(['entityDefs']) ?? []) as $entityType) {
            if ($this->util->getFieldList($entityType)) {
                $changed = $this->register($entityType) || $changed;
            }
        }

        return $changed;
    }
}
