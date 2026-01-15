<?php

namespace Algolia\SearchAdapter\Service\Product;

use Algolia\AlgoliaSearch\Helper\Entity\ProductHelper;

class MaxPriceProvider
{
    public array $maxPrices;

    public function __construct(
        protected ProductHelper $productHelper,
    ){}

    /**
     * @param int $storeId
     * @return float
     */
    public function getCatalogMaxPrice(int $storeId): float
    {
        if (!isset($this->maxPrices[$storeId])) {
            $collection = $this->productHelper->getProductCollectionQuery($storeId);
            $collection->setOrder("price", "desc");

            $this->maxPrices[$storeId] = $collection->getFirstItem() ?
                $collection->getFirstItem()->getFinalPrice() :
                0.00;
        }

        return $this->maxPrices[$storeId];
    }
}
