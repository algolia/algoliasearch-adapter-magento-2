<?php

namespace Algolia\SearchAdapter\Observer;

use Algolia\AlgoliaSearch\Helper\Configuration\InstantSearchHelper;
use Algolia\SearchAdapter\Helper\ConfigHelper;
use Algolia\SearchAdapter\Model\Config\Source\SortParam;
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
        $sortingParam = $this->configHelper->getSortingParameter(); //TODO: Refactor this lever to a mode
        $isMagentoCompatible = $sortingParam === SortParam::SORT_PARAM_MAGENTO;

        $configuration['routing'] = array_merge(
            $configuration['routing'] ?? [],
            [
                'isMagentoCompatible' => $isMagentoCompatible,
                'sortingParameter' => $this->configHelper->getSortingParameter(),
                'pagingParameter'  =>
                    $isMagentoCompatible ?
                        SortParam::PAGE_PARAM_MAGENTO :
                        SortParam::PAGE_PARAM_ALGOLIA,
                'categoryParameter' =>
                    $isMagentoCompatible ?
                        SortParam::CATEGORY_PARAM_MAGENTO :
                        SortParam::CATEGORY_PARAM_ALGOLIA,
                'priceParameter' =>
                    $isMagentoCompatible ?
                        SortParam::PRICE_PARAM_MAGENTO :
                        SortParam::PRICE_PARAM_MAGENTO . $configuration['priceKey'],
                'priceRouteSeparator' =>
                    $isMagentoCompatible ?
                        SortParam::PRICE_SEPARATOR_MAGENTO :
                        InstantSearchHelper::PRICE_SEPARATOR,
            ]
        );
    }
}
