<?php
namespace Algolia\SearchAdapter\Model\ResourceModel\Fulltext\Collection;

use Algolia\SearchAdapter\Registry\SortState;
use Algolia\SearchAdapter\ViewModel\Sorter;
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
        protected SortOrderBuilder      $sortOrderBuilder,
        protected SortState             $sortState,
        protected string                $searchRequestName,
        protected int                   $currentPage,
        protected int                   $size,
        protected ?array                $orders = null
    ) {}

    /**
     * @inheritdoc
     */
    public function resolve(): SearchCriteria
    {
        $searchCriteria = $this->builder->create();
        $searchCriteria->setRequestName($this->searchRequestName);
        $searchCriteria->setCurrentPage($this->currentPage - 1);
        if ($this->size) {
            $searchCriteria->setPageSize($this->size);
        }

        $this->applySorting($searchCriteria);

        return $searchCriteria;
    }

    protected function applySorting(SearchCriteria $searchCriteria): void
    {
        $sortOrders = $this->transformSortParams($this->orders);
        $searchCriteria->setSortOrders($sortOrders);
        if (count($sortOrders)) {
            $this->sortState->set(reset($sortOrders)); // maintain a single sort fallback due to core api instability
        }
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
        $parts = explode(Sorter::SORT_PARAM_DELIMITER, $param);
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
