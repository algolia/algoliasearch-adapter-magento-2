<?php

namespace Algolia\SearchAdapter\Service;

use Algolia\AlgoliaSearch\Api\Product\ReplicaManagerInterface;
use Algolia\AlgoliaSearch\Api\Product\RuleContextInterface;
use Algolia\AlgoliaSearch\Helper\Configuration\InstantSearchHelper;
use Algolia\AlgoliaSearch\Service\Product\PriceKeyResolver;
use Algolia\SearchAdapter\Api\Data\PaginationInfoInterface;
use Algolia\SearchAdapter\Api\Service\FilterHandlerInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Search\Request\Query\BoolExpression as BoolQuery;
use Magento\Framework\Search\Request\QueryInterface as RequestQueryInterface;
use Magento\Framework\Search\RequestInterface;

class QueryParamBuilder
{
    /** @var string  */
    public const FACET_PARAM_PRICE = 'price';
    /** @var string  */
    public const FACET_PARAM_HIERARCHICAL = 'categories';
    /**
     * Influences accuracy of product counts
     * See https://www.algolia.com/doc/api-reference/api-parameters/maxValuesPerFacet
     * @var int
     */
    public const MAX_VALUES_PER_FACET = 100;

    /**
     * @param InstantSearchHelper $instantSearchHelper
     * @param StoreIdResolver $storeIdResolver
     * @param PriceKeyResolver $priceKeyResolver
     * @param FilterHandlerInterface[] $filterHandlers
     */
    public function __construct(
        protected InstantSearchHelper $instantSearchHelper,
        protected StoreIdResolver     $storeIdResolver,
        protected PriceKeyResolver    $priceKeyResolver,
        protected array               $filterHandlers
    ) {}

    /**
     * Build the Algolia search query parameters
     */
    public function build(RequestInterface $request, PaginationInfoInterface $pagination): array
    {
        $storeId = $this->storeIdResolver->getStoreId($request);
        $params = [
            'hitsPerPage' => $pagination->getPageSize(),
            'page' => $pagination->getPageNumber() - 1, # Algolia pages are 0-based, Magento 1-based
            'facets' => $this->getFacetsToRetrieve($storeId),
            'maxValuesPerFacet' => $this->getMaxValuesPerFacet(),
            'ruleContexts' => [ RuleContextInterface::FACET_FILTERS_CONTEXT ]
        ];

        $filters = $this->getQueryFilters($request);

        if (empty($filters)) {
            return $params;
        }

        /** @var BoolQuery $requestQuery */
        $this->applyFilters($params, $filters, $storeId);

        return $params;
    }

    /**
     * Get filters from the search request query
     *
     * @return RequestQueryInterface[]
     */
    protected function getQueryFilters(RequestInterface $request): array
    {
        $requestQuery = $request->getQuery();
        if ($requestQuery->getType() !== RequestQueryInterface::TYPE_BOOL) {
            return [];
        }
        /** @var BoolQuery $requestQuery */
        return $requestQuery->getMust();
    }

    /**
     * Get facets to retrieve from the Algolia search request
     *
     * @return string[]
     */
    protected function getFacetsToRetrieve(int $storeId): array
    {
        try {
            $facets = array_map(
                fn($facet) => $this->transformFacetParam(
                    $facet[ReplicaManagerInterface::SORT_KEY_ATTRIBUTE_NAME],
                    $storeId
                ),
                $this->instantSearchHelper->getFacets($storeId)
            );
            // flatten all facets
            return array_merge(...$facets);
        } catch (NoSuchEntityException) {
            return [];
        }
    }

    /**
     * A single facet can be transformed to one or more facet attributes
     *
     * @return string[]
     * @throws NoSuchEntityException
     */
    protected function transformFacetParam(string $facet, int $storeId): array
    {
        if ($facet === self::FACET_PARAM_PRICE) {
            return [$facet . $this->priceKeyResolver->getPriceKey($storeId)];
        }

        if ($facet === self::FACET_PARAM_HIERARCHICAL) {
            return $this->splitHierarchicalFacet(self::FACET_PARAM_HIERARCHICAL);
        }

        return [$facet];
    }

    /*
     * Split the hierarchical facet into scalar levels
     *
     * @return string[]
     */
    protected function splitHierarchicalFacet(string $facet): array
    {
        $hierarchy = [];
        for ($level = 0; $level < 10; $level++) {
            $hierarchy[] = "$facet.level$level";
        }
        return $hierarchy;
    }

    /**
     * Apply the filters to the Algolia search query parameters
     *
     * @param array<string, mixed> $params
     * @param RequestQueryInterface[] $filters
     * @param int $storeId
     * @return void
     */
    protected function applyFilters(array &$params, array &$filters, int $storeId): void
    {
        foreach ($this->filterHandlers as $handler) {
            $handler->process($params, $filters, $storeId);
        }
    }

    public function getMaxValuesPerFacet(): int
    {
        return self::MAX_VALUES_PER_FACET;
    }
}
