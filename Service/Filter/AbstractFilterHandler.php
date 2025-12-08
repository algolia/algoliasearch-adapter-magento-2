<?php

namespace Algolia\SearchAdapter\Service\Filter;

use Algolia\SearchAdapter\Api\Service\FilterHandlerInterface;
use Magento\Framework\Search\Request\FilterInterface as RequestFilterInterface;
use Magento\Framework\Search\Request\Query\Filter as FilterQuery;
use Magento\Framework\Search\Request\QueryInterface as RequestQueryInterface;

abstract class AbstractFilterHandler implements FilterHandlerInterface
{
    /**
     * Obtain a parameter from the query filters in the original Magento search request for processing by a worker
     *
     * Returns false if criteria not met
     *
     * @param RequestQueryInterface[] $filters - the filters to be processed
     *     (values will be modified if $remove is set to true)
     * @param string $key - the filter to search for
     * @param bool $remove - Removes the filter if found
     *     (True by default in order to burn down the filters for processing)
     */
    protected function getFilterParam(array &$filters, string $key, bool $remove = true): string|array|false
    {
        $filter = $this->processFilterParam($filters, $key, $remove);

        if ($filter === false) {
            return false;
        }

        $term = $filter->getReference();
        if ($term->getType() !== RequestFilterInterface::TYPE_TERM) {
            return false;
        }

        return $term->getValue();
    }

    protected function processFilterParam(array &$filters, string $key, bool $remove = true): FilterQuery|false
    {
        if (!array_key_exists($key, $filters)) {
            return false;
        }
        $filter = $filters[$key];
        if ($remove) {
            unset($filters[$key]);
        }

        if ($filter->getType() !== RequestQueryInterface::TYPE_FILTER) {
            return false;
        }
        /** @var FilterQuery $filter */
        if ($filter->getReferenceType() !== FilterQuery::REFERENCE_FILTER) {
            return false;
        }

        return $filter;
    }

    /**
     * Apply a filter of a given type and value to the Algolia search query parameters array
     *
     * @param array<string, mixed> $params
     * @param string $filterType
     * @param string $filterValue
     */
    protected function applyFilter(array &$params, string $filterType, string $filterValue): void
    {
        if (!array_key_exists($filterType, $params)) {
            $params[$filterType] = [];
        }
        $params[$filterType][] = $filterValue;
    }
}
