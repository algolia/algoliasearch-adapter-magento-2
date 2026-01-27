<?php

namespace Algolia\SearchAdapter\Plugin\Service\Product;

use Algolia\AlgoliaSearch\Helper\ConfigHelper as CoreConfigHelper;
use Algolia\AlgoliaSearch\Service\Product\ReplicaManager;
use Algolia\SearchAdapter\Helper\ConfigHelper as AdapterConfigHelper;

class ReplicaManagerPlugin
{
    public function __construct(
        protected CoreConfigHelper $configHelper,
        protected AdapterConfigHelper $adapterConfigHelper,
    ) {}

    public function afterIsReplicaSyncEnabled(ReplicaManager $subject, bool $result, int $storeId): bool
    {
        return $result ||
            $this->adapterConfigHelper->isAlgoliaEngineSelected() && $this->configHelper->isIndexingEnabled($storeId);
    }
}
