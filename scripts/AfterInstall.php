<?php

use Espo\Core\Container;
use Espo\Core\DataManager;
use Espo\Core\InjectableFactory;
use Espo\Core\Utils\Metadata;
use Espo\Modules\Approvals\Tools\Approves\PdfRegistrar;
use Espo\Modules\Approvals\Tools\Approves\StatusTranslator;
use Espo\Modules\Approvals\Tools\Approves\Util;
use Espo\ORM\EntityManager;

/**
 * Runs after every install or upgrade.
 * 1. Fields from versions before 1.3 with 'Sequential' checked get Approval Mode 'Stages',
 *    so they keep working the same way and the admin sees the right option.
 * 2. Builds the search index (added in 1.5) for all existing records.
 * 3. Writes status translations for each Approves Status field (used by Kanban, added in 1.6.1).
 * 4. Registers the PDF data loader for entity types with Approves fields (added in 1.7).
 */
class AfterInstall
{
    public function run(Container $container): void
    {
        $metadata = $container->getByClass(Metadata::class);

        $changed = false;

        foreach (($metadata->get(['entityDefs']) ?? []) as $entityType => $defs) {
            foreach (($defs['fields'] ?? []) as $field => $fieldDefs) {
                if (
                    ($fieldDefs['type'] ?? null) !== 'approves' ||
                    empty($fieldDefs['sequential']) ||
                    !empty($fieldDefs['approvalMode'])
                ) {
                    continue;
                }

                // 'sequential' stays, so records saved before 1.3 keep their order as stages.
                $metadata->set('entityDefs', $entityType, [
                    'fields' => [
                        $field => ['approvalMode' => 'stages'],
                    ],
                ]);

                $changed = true;
            }
        }

        if ($changed) {
            $metadata->save();

            $container->getByClass(DataManager::class)->clearCache();
        }

        $this->rebuildIndexes($container);

        $injectableFactory = $container->getByClass(InjectableFactory::class);

        $injectableFactory->create(StatusTranslator::class)->writeAll();
        $injectableFactory->create(PdfRegistrar::class)->registerAll();

        $container->getByClass(DataManager::class)->clearCache();
    }

    private function rebuildIndexes(Container $container): void
    {
        $metadata = $container->getByClass(Metadata::class);
        $entityManager = $container->getByClass(EntityManager::class);
        $util = $container->getByClass(InjectableFactory::class)->create(Util::class);

        foreach (($metadata->get(['entityDefs']) ?? []) as $entityType => $defs) {
            foreach ($util->getFieldList($entityType) as $field) {
                $indexAttribute = Util::getIndexAttribute($field);

                if (!$entityManager->getDefs()->getEntity($entityType)->hasAttribute($indexAttribute)) {
                    continue;
                }

                $mode = $util->getMode($entityType, $field);

                $collection = $entityManager->getRDBRepository($entityType)
                    ->select(['id', $field])
                    ->sth()
                    ->find();

                foreach ($collection as $entity) {
                    $list = $util->normalizeForField($entityType, $field, $entity->get($field));

                    // A direct update: no hooks, Stream entries, notifications or 'modified' changes.
                    $update = $entityManager->getQueryBuilder()
                        ->update()
                        ->in($entityType)
                        ->set([$indexAttribute => $util->buildIndex($list, $mode)])
                        ->where(['id' => $entity->getId()])
                        ->build();

                    $entityManager->getQueryExecutor()->execute($update);
                }
            }
        }
    }
}
