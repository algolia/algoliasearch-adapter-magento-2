<?php
namespace Algolia\SearchAdapter\Model\ResourceModel\Fulltext\Collection;

use Magento\CatalogSearch\Model\ResourceModel\Fulltext\Collection\SearchCriteriaResolverInterface;
use Magento\Framework\Api\Search\SearchCriteriaBuilder;
use Magento\Framework\Api\Search\SearchCriteria;

/**
 * Resolve specific attributes for Algolia search criteria.
 */
class SearchCriteriaResolver implements SearchCriteriaResolverInterface
{
    public function __construct(
        protected SearchCriteriaBuilder $builder,
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
        $searchCriteria->setSortOrders($this->orders);
        $searchCriteria->setCurrentPage($this->currentPage - 1);
        if ($this->size) {
            $searchCriteria->setPageSize($this->size);
        }

        return $searchCriteria;
    }
}
