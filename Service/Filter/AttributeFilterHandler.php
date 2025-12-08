<?php

namespace Algolia\SearchAdapter\Service\Filter;

use Algolia\AlgoliaSearch\Api\Product\ReplicaManagerInterface;
use Algolia\AlgoliaSearch\Helper\Configuration\InstantSearchHelper;
use Algolia\SearchAdapter\Service\FacetValueConverter;
use Magento\Framework\Search\Request\Filter\Term;
use Magento\Framework\Search\Request\FilterInterface as RequestFilterInterface;
use Magento\Framework\Search\Request\Query\Filter as FilterQuery;
use Magento\Framework\Search\Request\QueryInterface as RequestQueryInterface;

class AttributeFilterHandler extends AbstractFilterHandler
{
    public function __construct(
        protected InstantSearchHelper $instantSearchHelper,
        protected FacetValueConverter $facetValueConverter,
    ) {}

    /*
     * Apply the facet filters to the Algolia search query parameters
     * This will filter the results returned by Algolia to only include products that match the facet values applied
     *
     * @param array<string, mixed> $params
     * @param RequestQueryInterface[] $filters
     */
    public function process(array &$params, array &$filters, ?int $storeId = null): void
    {
        if (empty($filters)) {
            return;
        }

        $facets = $this->instantSearchHelper->getFacets($storeId);

        foreach ($filters as $key => $filter) {
            $term = $this->getFacetFilterTerm($filter);
            if (!$term) {
                continue;
            }
            $facetName = $term->getField();
            $facet = $this->getMatchedFacet($facets, $facetName);
            if (!$facet) {
                continue;
            }
            $value = $this->facetValueConverter->convertOptionIdToLabel($facetName, $term->getValue());
            $this->applyFilter($params, 'facetFilters', sprintf('%s:%s', $facetName, $value));

            unset($filters[$key]); // burn down filters
        }
    }

    /**
     * This will return the term from the filter query if it is a term filter
     */
    protected function getFacetFilterTerm(RequestQueryInterface $filter): ?Term
    {
        if ($filter->getType() !== RequestQueryInterface::TYPE_FILTER) {
            return null;
        }
        /** @var FilterQuery $filter */
        $term = $filter->getReference();
        if ($term->getType() !== RequestFilterInterface::TYPE_TERM) {
            return null;
        }
        return $term;
    }

    /**
     * This will return the matched facet from the configured facets if it exists
     *
     * @param array<string, mixed> $facets
     * @param string $facetName
     * @param bool $remove - Removes the facet if found
     *     (True by default in order to burn down the facets for processing)
     * @return array<string, mixed>|null
     */
    protected function getMatchedFacet(array &$facets, string $facetName, bool $remove = true): ?array {
        foreach ($facets as $i => $facet) {
            if ($facet[ReplicaManagerInterface::SORT_KEY_ATTRIBUTE_NAME] === $facetName) {
                if ($remove) {
                    unset($facets[$i]);
                }
                return $facet;
            }
        }
        return null;
    }
}
