<?php

namespace Algolia\SearchAdapter\Plugin\Indexer;

use Algolia\SearchAdapter\Helper\ConfigHelper as AdapterConfigHelper;
use Magento\CatalogSearch\Model\Indexer\Fulltext as EsFulltextIndexer;

class DisableElasticSearchIndexing
{
    public function __construct(
        protected AdapterConfigHelper $adapterConfigHelper,
    ){}

    /**
     * @param EsFulltextIndexer $subject
     * @param callable $proceed
     * @param $entityIds
     * @return void
     */
    public function aroundExecute(EsFulltextIndexer $subject, callable $proceed, $entityIds): void
    {
        if ($this->adapterConfigHelper->isElasticSearchDisabled()) {
            return;
        }

        $proceed($entityIds);
    }

    /**
     * @param EsFulltextIndexer $subject
     * @param callable $proceed
     * @return void
     */
    public function aroundExecuteFull(EsFulltextIndexer $subject, callable $proceed): void
    {
        if ($this->adapterConfigHelper->isElasticSearchDisabled()) {
            return;
        }

        $proceed();
    }

    /**
     * @param EsFulltextIndexer $subject
     * @param callable $proceed
     * @param array $dimensions
     * @param \Traversable|null $entityIds
     * @return void
     */
    public function aroundExecuteByDimensions(
        EsFulltextIndexer $subject,
        callable $proceed,
        array $dimensions,
        ?\Traversable $entityIds = null
    ): void {
        if ($this->adapterConfigHelper->isElasticSearchDisabled()) {
            return;
        }

        $proceed($dimensions, $entityIds);
    }
}
