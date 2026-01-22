<?php

namespace Algolia\SearchAdapter\Observer;

use Algolia\AlgoliaSearch\Helper\Configuration\InstantSearchHelper;
use Algolia\SearchAdapter\Helper\ConfigHelper;
use Algolia\SearchAdapter\Model\Config\Source\QueryStringParamMode;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;

class UpdateConfiguration implements ObserverInterface
{
    public function __construct(
        protected ConfigHelper $configHelper
    ) {}

    public function execute(Observer $observer): void
    {
        $configuration = $observer->getData('configuration');
        $queryStringParamMode = $this->configHelper->getQueryStringParamMode();
        $isMagentoCompatible = $queryStringParamMode === QueryStringParamMode::PARAM_MODE_MAGENTO;

        $configuration['routing'] = array_merge(
            $configuration['routing'] ?? [],
            [
                'isMagentoCompatible' => $isMagentoCompatible,
                'sortingParameter' =>
                    $isMagentoCompatible ?
                        QueryStringParamMode::SORT_PARAM_MAGENTO :
                        QueryStringParamMode::SORT_PARAM_ALGOLIA,
                'pagingParameter'  =>
                    $isMagentoCompatible ?
                        QueryStringParamMode::PAGE_PARAM_MAGENTO :
                        QueryStringParamMode::PAGE_PARAM_ALGOLIA,
                'categoryParameter' =>
                    $isMagentoCompatible ?
                        QueryStringParamMode::CATEGORY_PARAM_MAGENTO :
                        QueryStringParamMode::CATEGORY_PARAM_ALGOLIA,
                'priceParameter' =>
                    $isMagentoCompatible ?
                        QueryStringParamMode::PRICE_PARAM_MAGENTO :
                        QueryStringParamMode::PRICE_PARAM_MAGENTO . $configuration['priceKey'],
                'priceRouteSeparator' =>
                    $isMagentoCompatible ?
                        QueryStringParamMode::PRICE_SEPARATOR_MAGENTO :
                        InstantSearchHelper::PRICE_SEPARATOR,
            ]
        );
    }
}
