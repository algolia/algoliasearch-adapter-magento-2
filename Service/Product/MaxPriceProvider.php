<?php

namespace Algolia\SearchAdapter\Service\Product;

use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory;

class MaxPriceProvider
{
    public array $maxPrices;

    public function __construct(
        protected CollectionFactory $productCollectionFactory,
    ){}

    /**
     * @param int $storeId
     * @return float
     */
    public function getCatalogMaxPrice(int $storeId): float
    {
        if (!isset($this->maxPrices[$storeId])) {
            $collection = $this->productCollectionFactory->create();
            $collection->setStoreId($storeId);
            $collection->addAttributeToSort("price", "desc");

            $this->maxPrices[$storeId] = $collection->getFirstItem() ?
                $collection->getFirstItem()->getFinalPrice() :
                0.00;
        }

        return $this->maxPrices[$storeId];
    }
}
