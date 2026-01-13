<?php

namespace Algolia\SearchAdapter\Plugin\Model\Layer\Filter;

use Algolia\SearchAdapter\Helper\ConfigHelper;
use Algolia\SearchAdapter\Model\Source\SortParam;
use Algolia\SearchAdapter\Service\Product\MaxPriceProvider;
use Magento\CatalogSearch\Model\Layer\Filter\Price;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Store\Model\StoreManagerInterface;

class PricePlugin
{
    public function __construct(
        protected ConfigHelper          $configHelper,
        protected StoreManagerInterface $storeManager,
        protected MaxPriceProvider      $maxPriceProvider,
    ) {}

    /**
     * @throws LocalizedException
     */
    public function beforeApply(Price $subject, RequestInterface $request): array
    {
        $attributeCode = $subject->getRequestVar();
        $attributeValue = $request->getParam($attributeCode);
        $storeId = $this->storeManager->getStore()->getId();

        if (
            !$this->configHelper->areSeoFiltersEnabled($storeId)
            ||
            $attributeValue === null
            ||
            trim($attributeValue) === ''
        ) {
            return [$request];
        }

        $maxPrice = $this->maxPriceProvider->getCatalogMaxPrice($storeId);
        $boundaries = explode(SortParam::PRICE_SEPARATOR_MAGENTO, $attributeValue);
        $boundaries[0] = isset($boundaries[0]) && $boundaries[0] !== "" ? $boundaries[0] : "0";
        $boundaries[1] = isset($boundaries[1]) && $boundaries[1] !== "" ? $boundaries[1] : $maxPrice;

        $request->setParam(
            $attributeCode,
            implode(SortParam::PRICE_SEPARATOR_MAGENTO, $boundaries)
        );

        return [$request];
    }
}
