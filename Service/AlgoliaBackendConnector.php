<?php

namespace Algolia\SearchAdapter\Service;

use Algolia\AlgoliaSearch\Api\SearchClientProviderInterface;
use Algolia\AlgoliaSearch\Helper\ConfigHelper as BaseConfigHelper;
use Algolia\AlgoliaSearch\Service\AlgoliaConnector;
use Algolia\AlgoliaSearch\Service\IndexNameFetcher;
use Algolia\AlgoliaSearch\Service\IndexOptionsBuilder;
use Algolia\AlgoliaSearch\Service\SendStrategyResolver;
use Algolia\SearchAdapter\Helper\ConfigHelper;
use Magento\Framework\Message\ManagerInterface;
use Symfony\Component\Console\Output\ConsoleOutput;

class AlgoliaBackendConnector extends AlgoliaConnector
{
    public function __construct(
        protected ConfigHelper        $adapterConfig,
        BaseConfigHelper              $config,
        ManagerInterface              $messageManager,
        ConsoleOutput                 $consoleOutput,
        SearchClientProviderInterface $clientProvider,
        IndexNameFetcher              $indexNameFetcher,
        IndexOptionsBuilder           $indexOptionsBuilder,
        SendStrategyResolver          $sendStrategyResolver
    )
    {
        return parent::__construct(
            $config,
            $messageManager,
            $consoleOutput,
            $clientProvider,
            $indexNameFetcher,
            $indexOptionsBuilder,
            $sendStrategyResolver
        );
    }

    /** The backend connector uses distinct timeout configurations from the indexer  */
    protected function getConnectionTimeout(int $storeId): int
    {
        return $this->adapterConfig->getConnectionTimeout($storeId);
    }

    /** The backend connector uses distinct timeout configurations from the indexer  */
    protected function getReadTimeout(int $storeId): int
    {
        return $this->adapterConfig->getReadTimeout($storeId);
    }

}
