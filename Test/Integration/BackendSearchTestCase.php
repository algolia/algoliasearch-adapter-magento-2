<?php

namespace Algolia\SearchAdapter\Test\Integration;

use Algolia\AlgoliaSearch\Test\Integration\Indexing\Product\ProductsIndexingTestCase;

class BackendSearchTestCase extends ProductsIndexingTestCase
{
    protected function indexAllProducts(int $storeId = 1): void
    {
        $this->productBatchQueueProcessor->processBatch($storeId);
        $this->algoliaConnector->waitLastTask();
    }
}
