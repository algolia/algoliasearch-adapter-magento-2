<?php

namespace Algolia\SearchAdapter\Helper;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;

class ConfigHelper
{
    public const DISABLE_ES = 'algoliasearch_advanced/search_adapter/disable_es';

    public function __construct(
        protected ScopeConfigInterface $configInterface
    ){}

    /**
     * @return bool
     */
    public function isElasticSearchDisabled(): bool
    {
        return $this->configInterface->isSetFlag(self::DISABLE_ES, ScopeInterface::SCOPE_STORE);
    }
}
