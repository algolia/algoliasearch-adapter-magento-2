<?php

namespace Algolia\SearchAdapter\Helper;

use Algolia\AlgoliaSearch\Helper\ConfigHelper as BaseConfigHelper;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Search\EngineResolverInterface;
use Magento\Store\Model\ScopeInterface;

class ConfigHelper
{
    public const ALGOLIA_ENGINE = "algolia";

    // Backend engine configuration
    public const CONNECTION_TIMEOUT = 'catalog/search/algolia_connect_timeout';
    public const READ_TIMEOUT = 'catalog/search/algolia_read_timeout';
    public const SEO_FILTERS = 'catalog/search/algolia_seo_filters';
    public const SORTING_PARAMETER = 'algoliasearch_backend/algolia_backend_listing/sort_param';

    public function __construct(
        protected EngineResolverInterface $engineResolver,
        protected ScopeConfigInterface    $configInterface,
    ) {}

    /**
     * @return bool
     */
    public function isAlgoliaEngineSelected(): bool
    {
        return $this->engineResolver->getCurrentSearchEngine() === self::ALGOLIA_ENGINE;
    }

    public function isEngineSelectEnabled(RequestInterface $request): bool
    {
        return !$request->getParam('store') && !$request->getParam('website');
    }

    public function isEngineSelectVisible(RequestInterface $request): bool
    {
        return $this->isEngineSelectEnabled($request)  ||
            !$this->isEngineSelectEnabled($request) && $this->isAlgoliaEngineSelected();
    }

    public function areSeoFiltersEnabled(?int $storeId = null): bool
    {
        return $this->configInterface->getValue(self::SEO_FILTERS, ScopeInterface::SCOPE_STORES, $storeId);
    }

    /**
     * Get value scoped by website or store (required for website context based admin functions)
     */
    protected function getConfigByScope(string $path, ?int $websiteId = null, ?int $storeId = null): string
    {

        if ($websiteId !== null) {
            $scope = ScopeInterface::SCOPE_WEBSITES;
            $scopeId = $websiteId;
        } elseif ($storeId !== null) {
            $scope = ScopeInterface::SCOPE_STORES;
            $scopeId = $storeId;
        } else {
            $scope = ScopeConfigInterface::SCOPE_TYPE_DEFAULT;
            $scopeId = null;
        }
        return $this->configInterface->getValue($path, $scope, $scopeId);
    }

    /**
     * Get admin scoped App ID
     */
    public function getApplicationID(?int $websiteId = null, ?int $storeId = null): string
    {
        return $this->getConfigByScope(BaseConfigHelper::APPLICATION_ID, $websiteId, $storeId);
    }

    /**
     * Get admin scoped API key
     */
    public function getApiKey(?int $websiteId = null, ?int $storeId = null): string
    {
        return $this->getConfigByScope(BaseConfigHelper::API_KEY, $websiteId, $storeId);
    }

    /**
     * Backend search uses different timeout settings from indexer
     * Only used by frontend not adminhtml scoped
     */
    public function getConnectionTimeout(?int $storeId = null): int
    {
        return $this->getConfigByScope(self::CONNECTION_TIMEOUT, null, $storeId);
    }

    /**
     * Backend search uses different timeout settings from indexer
     * Only used by frontend not adminhtml scoped
     */
    public function getReadTimeout(?int $storeId = null): int
    {
        return $this->getConfigByScope(self::READ_TIMEOUT, null, $storeId);
    }

    public function getSortingParameter(?int $storeId = null): string
    {
        return (string) $this->configInterface->getValue(
            self::SORTING_PARAMETER,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }
}
