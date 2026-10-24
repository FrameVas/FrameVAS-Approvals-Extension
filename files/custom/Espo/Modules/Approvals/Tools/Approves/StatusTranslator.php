<?php

namespace Espo\Modules\Approvals\Tools\Approves;

use Espo\Core\Utils\File\Manager as FileManager;
use Espo\Core\Utils\Language\LanguageFactory;
use Espo\Core\Utils\Metadata;

/**
 * Some views (e.g. Kanban columns) translate enum values only from the entity's own option
 * translations ({Entity}.options.{field}) and ignore the field's 'translation' parameter.
 * This writes the status names for each Approves Status field into every language,
 * as custom labels, like the Label Manager does.
 */
class StatusTranslator
{
    public function __construct(
        private Metadata $metadata,
        private LanguageFactory $languageFactory,
        private FileManager $fileManager,
    ) {}

    /**
     * @param bool $overwrite Replace existing labels (e.g. ones edited in the Label Manager).
     */
    public function write(string $entityType, string $field, bool $overwrite = false): void
    {
        $attribute = Util::getStatusAttribute($field);

        /** @var string[] $languageList */
        $languageList = $this->metadata->get(['app', 'language', 'list']) ?? ['en_US'];

        foreach ($languageList as $languageCode) {
            // Checks this language's own custom labels only: Language->get() falls back to English.
            if (!$overwrite && $this->hasCustomLabel($languageCode, $entityType, $attribute)) {
                continue;
            }

            $language = $this->languageFactory->create($languageCode);

            $map = $language->get(['Global', 'options', 'approvesStatus']);

            if (!is_array($map) || !$map) {
                continue;
            }

            // Languages the extension doesn't translate fall back to English by themselves.
            // Copying English there would block translations added later.
            if ($languageCode !== 'en_US' && $map === $this->getEnglishMap()) {
                continue;
            }

            $language->set($entityType, 'options', $attribute, $map);
            $language->save();
        }
    }

    /** @var ?array<string, string> */
    private ?array $englishMap = null;

    /**
     * @return array<string, string>
     */
    private function getEnglishMap(): array
    {
        if ($this->englishMap === null) {
            $map = $this->languageFactory->create('en_US')->get(['Global', 'options', 'approvesStatus']);

            $this->englishMap = is_array($map) ? $map : [];
        }

        return $this->englishMap;
    }

    private function hasCustomLabel(string $languageCode, string $entityType, string $attribute): bool
    {
        $path = 'custom/Espo/Custom/Resources/i18n/' . basename($languageCode) . '/' . basename($entityType) . '.json';

        if (!$this->fileManager->isFile($path)) {
            return false;
        }

        $data = json_decode($this->fileManager->getContents($path), true);

        return !empty($data['options'][$attribute]);
    }

    /**
     * Writes translations for all existing Approves fields.
     */
    public function writeAll(): void
    {
        foreach (($this->metadata->get(['entityDefs']) ?? []) as $entityType => $defs) {
            foreach (($defs['fields'] ?? []) as $field => $fieldDefs) {
                if (($fieldDefs['type'] ?? null) === Util::FIELD_TYPE) {
                    $this->write($entityType, $field);
                }
            }
        }
    }
}
