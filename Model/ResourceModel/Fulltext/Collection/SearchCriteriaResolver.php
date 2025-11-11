<?php
namespace Algolia\SearchAdapter\Model\ResourceModel\Fulltext\Collection;

use Magento\CatalogSearch\Model\ResourceModel\Fulltext\Collection\SearchCriteriaResolverInterface;
use Magento\Framework\Api\Search\SearchCriteriaBuilder;
use Magento\Framework\Api\Search\SearchCriteria;
use Magento\Framework\Api\SortOrder;
use Magento\Framework\Api\SortOrderBuilder;

/**
 * Resolve specific attributes for Algolia search criteria.
 */
class SearchCriteriaResolver implements SearchCriteriaResolverInterface
{
    public function __construct(
        protected SearchCriteriaBuilder $builder,
        protected SortOrderBuilder $sortOrderBuilder,
        protected string $searchRequestName,
        protected int $currentPage,
        protected int $size,
        protected ?array $orders = null
    ) {}

    /**
     * @inheritdoc
     */
    public function resolve(): SearchCriteria
    {
        $searchCriteria = $this->builder->create();
        $searchCriteria->setRequestName($this->searchRequestName);
        $searchCriteria->setSortOrders($this->transformSortParams($this->orders));
        $searchCriteria->setCurrentPage($this->currentPage - 1);
        if ($this->size) {
            $searchCriteria->setPageSize($this->size);
        }
        return $searchCriteria;
    }

    /** We only care about Algolia sorting params - parse and filter out the rest  */
    protected function transformSortParams(?array $orders): array {
        if (!$orders) {
            return [];
        }
        return array_values(
            array_filter(
                array_map([$this, 'parseSortParam'], array_keys($orders)
            )
        ));
    }

    protected function parseSortParam(string $param): ?SortOrder
    {
        $parts = explode(\Algolia\SearchAdapter\ViewModel\Sorter::SORT_PARAM_DELIMITER, $param);
        if (count($parts) < 2) {
            return null;
        }
        list($fieldName, $direction) = $parts;
        return $this->sortOrderBuilder
            ->setField($fieldName)
            ->setDirection(strtoupper($direction) == 'DESC' ? SortOrder::SORT_DESC : SortOrder::SORT_ASC)
            ->create();
    }
}
