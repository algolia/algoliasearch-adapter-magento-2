<?php

namespace Algolia\SearchAdapter\Test\Traits;

use Magento\Framework\Search\Request\Filter\Term;
use Magento\Framework\Search\Request\FilterInterface;
use Magento\Framework\Search\Request\Query\BoolExpression as BoolQuery;
use Magento\Framework\Search\Request\Query\Filter as FilterQuery;
use Magento\Framework\Search\Request\Query\MatchQuery;
use Magento\Framework\Search\Request\QueryInterface;
use Magento\Framework\Search\RequestInterface;
use PHPUnit\Framework\MockObject\MockObject;

/**
 * Trait providing common mock creation methods for query-related tests
 */
trait QueryTestTrait
{
    /**
     * Create a mock RequestInterface
     */
    protected function createMockRequest(): RequestInterface|MockObject
    {
        return $this->createMock(RequestInterface::class);
    }

    /**
     * Create a generic mock query (no specific type)
     */
    protected function createGenericMockQuery(): QueryInterface|MockObject
    {
        return $this->createMock(QueryInterface::class);
    }

    /**
     * Create a mock BoolQuery with TYPE_BOOL already configured
     */
    protected function createMockBoolQuery(): BoolQuery|MockObject
    {
        $boolQuery = $this->createMock(BoolQuery::class);
        $boolQuery->method('getType')->willReturn(QueryInterface::TYPE_BOOL);
        return $boolQuery;
    }

    /**
     * Create a mock MatchQuery with optional query value
     */
    protected function createMockMatchQuery(?string $query = null): MatchQuery|MockObject
    {
        $matchQuery = $this->createMock(MatchQuery::class);
        if ($query) {
            $matchQuery->method('getValue')->willReturn($query);
        }
        return $matchQuery;
    }

    /**
     * Create a mock FilterQuery with nested mock term
     *
     * @param mixed $value The value to be returned by the term filter (null returns minimal mock)
     */
    protected function createMockFilterQuery(mixed $value = null): FilterQuery|MockObject
    {
        $filterQuery = $this->createMock(FilterQuery::class);
        if ($value === null) {
            return $filterQuery;
        }
        $filterQuery->method('getType')->willReturn(QueryInterface::TYPE_FILTER);
        $filterQuery->method('getReferenceType')->willReturn(FilterQuery::REFERENCE_FILTER);

        $termFilter = $this->createMockTermFilter($value);
        $filterQuery->method('getReference')->willReturn($termFilter);

        return $filterQuery;
    }

    /**
     * Create a mock Term filter
     *
     * @param mixed $value The value to be returned by the term filter (null for no value configured)
     */
    protected function createMockTermFilter(mixed $value = null): Term|MockObject
    {
        $termFilter = $this->createMock(Term::class);
        $termFilter->method('getType')->willReturn(FilterInterface::TYPE_TERM);
        if ($value !== null) {
            $termFilter->method('getValue')->willReturn($value);
        }
        return $termFilter;
    }
}

