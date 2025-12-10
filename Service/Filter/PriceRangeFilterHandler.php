<?php

namespace Algolia\SearchAdapter\Service\Filter;

use Algolia\AlgoliaSearch\Service\Product\PriceKeyResolver;
use Algolia\SearchAdapter\Service\QueryParamBuilder;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Search\Request\Filter\Range;
use Magento\Framework\Search\Request\FilterInterface as RequestFilterInterface;
use Magento\Framework\Search\Request\QueryInterface as RequestQueryInterface;

class PriceRangeFilterHandler extends AbstractFilterHandler
{
    public function __construct(
        protected PriceKeyResolver $priceKeyResolver,
    ) {}
    /**
     * @inheritDoc
     * @throws NoSuchEntityException
     */
    public function process(array &$params, array &$filters, ?int $storeId = null): void
    {
        $range = $this->getRangeFilter($filters, 'price');
        if (!$range) {
            return;
        }

        $priceFacet = QueryParamBuilder::FACET_PARAM_PRICE . $this->priceKeyResolver->getPriceKey($storeId);
        $this->applyFilter(
            $params,
            'numericFilters',
            sprintf('%s>=%.3f', $priceFacet, $range->getFrom())
        );
        $this->applyFilter(
            $params,
            'numericFilters',
            sprintf('%s<=%.3f', $priceFacet, $range->getTo())
        );
    }

    /**
     * Obtain the price range from the query filters in the original Magento search request
     *
     * @param RequestQueryInterface[] $filters - the filters to be processed
     *     (values will be modified if $remove is set to true)
     * @param string $key - the filter to search for
     * @param bool $remove - Removes the filter if found
     *     (True by default in order to burn down the filters for processing)
     */
    protected function getRangeFilter(array &$filters, string $key, bool $remove = true): ?Range
    {
        $filter = $this->processFilterParam($filters, $key, $remove);

        if ($filter === false) {
            return null;
        }

        $range = $filter->getReference();
        if ($range->getType() !== RequestFilterInterface::TYPE_RANGE) {
            return null;
        }

        return $range;
    }
}
