<?php

namespace Algolia\SearchAdapter\Model\ElasticSearchOverride\Indexer;

use Algolia\SearchAdapter\Helper\ConfigHelper as AdapterConfigHelper;
use Magento\CatalogSearch\Model\Indexer\Fulltext as EsFulltextIndexer;
use Magento\CatalogSearch\Model\Indexer\Fulltext\Action\FullFactory;
use Magento\CatalogSearch\Model\Indexer\IndexerHandlerFactory;
use Magento\CatalogSearch\Model\Indexer\IndexSwitcherInterface;
use Magento\CatalogSearch\Model\Indexer\Scope\StateFactory;
use Magento\CatalogSearch\Model\ResourceModel\Fulltext as FulltextResource;
use Magento\Framework\App\DeploymentConfig;
use Magento\Framework\Indexer\DimensionProviderInterface;
use Magento\Indexer\Model\ProcessManager;

class Fulltext extends EsFulltextIndexer
{
    public function __construct(
        protected AdapterConfigHelper $adapterConfigHelper,
        FullFactory $fullActionFactory,
        IndexerHandlerFactory $indexerHandlerFactory,
        FulltextResource $fulltextResource,
        IndexSwitcherInterface $indexSwitcher,
        StateFactory $indexScopeStateFactory,
        DimensionProviderInterface $dimensionProvider,
        array $data = [],
        ?ProcessManager $processManager = null,
        ?int $batchSize = null,
        ?DeploymentConfig $deploymentConfig = null,
    ){
        parent::__construct(
            $fullActionFactory,
            $indexerHandlerFactory,
            $fulltextResource,
            $indexSwitcher,
            $indexScopeStateFactory,
            $dimensionProvider,
            $data,
            $batchSize,
            $deploymentConfig,
        );
    }

    /**
     * @param $entityIds
     * @return void
     */
    public function execute($entityIds): void
    {
        if ($this->adapterConfigHelper->isElasticSearchDisabled()) {
            return;
        }

        parent::execute($entityIds);
    }

    /**
     * @return void
     */
    public function executeFull(): void
    {
        if ($this->adapterConfigHelper->isElasticSearchDisabled()) {
            return;
        }

        parent::executeFull();
    }

    /**
     * @param array $dimensions
     * @param \Traversable|null $entityIds
     * @return void
     * @throws \Exception
     */
    public function executeByDimensions(array $dimensions, ?\Traversable $entityIds = null): void
    {
        if ($this->adapterConfigHelper->isElasticSearchDisabled()) {
            return;
        }

        parent::executeByDimensions($dimensions, $entityIds);
    }
}
