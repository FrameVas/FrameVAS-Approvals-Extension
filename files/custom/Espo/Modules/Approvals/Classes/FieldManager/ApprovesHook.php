<?php

namespace Espo\Modules\Approvals\Classes\FieldManager;

use Espo\Core\DataManager;
use Espo\Modules\Approvals\Tools\Approves\PdfRegistrar;
use Espo\Modules\Approvals\Tools\Approves\StatusTranslator;

/**
 * Runs when an admin creates or saves an Approves field in the Entity Manager.
 */
class ApprovesHook
{
    public function __construct(
        private StatusTranslator $statusTranslator,
        private PdfRegistrar $pdfRegistrar,
        private DataManager $dataManager,
    ) {}

    /**
     * @param array<string, mixed> $defs
     * @param array<string, mixed> $options
     */
    public function afterSave(string $scope, string $name, $defs, $options): void
    {
        $this->statusTranslator->write($scope, $name);

        if ($this->pdfRegistrar->register($scope)) {
            $this->dataManager->clearCache();
        }
    }
}
