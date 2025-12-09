<?php

namespace Algolia\SearchAdapter\Test\Unit\Service\Filter;

use Algolia\AlgoliaSearch\Service\Product\PriceKeyResolver;
use Algolia\AlgoliaSearch\Test\TestCase;
use Algolia\SearchAdapter\Service\Filter\PriceRangeFilterHandler;
use Algolia\SearchAdapter\Service\QueryParamBuilder;
use Algolia\SearchAdapter\Test\Traits\QueryTestTrait;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Search\Request\Filter\Range;
use Magento\Framework\Search\Request\FilterInterface as RequestFilterInterface;
use Magento\Framework\Search\Request\Query\Filter as FilterQuery;
use Magento\Framework\Search\Request\QueryInterface as RequestQueryInterface;
use PHPUnit\Framework\MockObject\MockObject;

class PriceRangeFilterHandlerTest extends TestCase
{
    use QueryTestTrait;

    private PriceRangeFilterHandler $handler;
    private PriceKeyResolver|MockObject $priceKeyResolver;

    protected function setUp(): void
    {
        $this->priceKeyResolver = $this->createMock(PriceKeyResolver::class);
        $this->handler = new PriceRangeFilterHandler($this->priceKeyResolver);
    }

    public function testProcessWithPriceRangeFilter(): void
    {
        $storeId = 1;
        $filters = ['price' => $this->createRangeFilterQuery(10.00, 99.99)];
        $params = [];

        $this->priceKeyResolver->method('getPriceKey')->with($storeId)->willReturn('.USD.default');

        $this->handler->process($params, $filters, $storeId);

        $expectedPriceFacet = QueryParamBuilder::FACET_PARAM_PRICE . '.USD.default';
        $this->assertEquals([
            'numericFilters' => [
                sprintf('%s>=%.3f', $expectedPriceFacet, 10.00),
                sprintf('%s<=%.3f', $expectedPriceFacet, 99.99)
            ]
        ], $params);
        $this->assertCount(0, $filters, 'Filters should burn down correctly');
    }

    public function testProcessWithoutPriceFilter(): void
    {
        $storeId = 1;
        $otherQuery = $this->createMock(RequestQueryInterface::class);
        $filters = ['other' => $otherQuery];
        $params = [];

        $this->handler->process($params, $filters, $storeId);

        $this->assertEquals([], $params);
        $this->assertCount(1, $filters, 'Non-matching filters should remain');
    }

    public function testProcessWithDifferentPriceKey(): void
    {
        $storeId = 2;
        $filters = ['price' => $this->createRangeFilterQuery(50.00, 200.00)];
        $params = [];

        $this->priceKeyResolver->method('getPriceKey')->with($storeId)->willReturn('.EUR.group_2');

        $this->handler->process($params, $filters, $storeId);

        $expectedPriceFacet = QueryParamBuilder::FACET_PARAM_PRICE . '.EUR.group_2';
        $this->assertEquals([
            'numericFilters' => [
                sprintf('%s>=%.3f', $expectedPriceFacet, 50.00),
                sprintf('%s<=%.3f', $expectedPriceFacet, 200.00)
            ]
        ], $params);
    }

    public function testProcessWithExistingNumericFilters(): void
    {
        $storeId = 1;
        $filters = ['price' => $this->createRangeFilterQuery(25.00, 75.00)];
        $params = ['numericFilters' => ['visibility_search=1']];

        $this->priceKeyResolver->method('getPriceKey')->with($storeId)->willReturn('.USD.default');

        $this->handler->process($params, $filters, $storeId);

        $expectedPriceFacet = QueryParamBuilder::FACET_PARAM_PRICE . '.USD.default';
        $this->assertEquals([
            'numericFilters' => [
                'visibility_search=1',
                sprintf('%s>=%.3f', $expectedPriceFacet, 25.00),
                sprintf('%s<=%.3f', $expectedPriceFacet, 75.00)
            ]
        ], $params);
    }

    public function testProcessWithZeroPriceRange(): void
    {
        $storeId = 1;
        $filters = ['price' => $this->createRangeFilterQuery(0.00, 100.00)];
        $params = [];

        $this->priceKeyResolver->method('getPriceKey')->with($storeId)->willReturn('.USD.default');

        $this->handler->process($params, $filters, $storeId);

        $expectedPriceFacet = QueryParamBuilder::FACET_PARAM_PRICE . '.USD.default';
        $this->assertEquals([
            'numericFilters' => [
                sprintf('%s>=%.3f', $expectedPriceFacet, 0.00),
                sprintf('%s<=%.3f', $expectedPriceFacet, 100.00)
            ]
        ], $params);
    }

    public function testProcessWithDecimalPrices(): void
    {
        $storeId = 1;
        $filters = ['price' => $this->createRangeFilterQuery(19.995, 49.995)];
        $params = [];

        $this->priceKeyResolver->method('getPriceKey')->with($storeId)->willReturn('.USD.default');

        $this->handler->process($params, $filters, $storeId);

        $expectedPriceFacet = QueryParamBuilder::FACET_PARAM_PRICE . '.USD.default';
        $this->assertEquals([
            'numericFilters' => [
                sprintf('%s>=%.3f', $expectedPriceFacet, 19.995),
                sprintf('%s<=%.3f', $expectedPriceFacet, 49.995)
            ]
        ], $params);
    }

    public function testProcessWithEmptyFilters(): void
    {
        $storeId = 1;
        $filters = [];
        $params = [];

        $this->handler->process($params, $filters, $storeId);

        $this->assertEquals([], $params);
        $this->assertCount(0, $filters);
    }

    public function testProcessWithNonRangeFilter(): void
    {
        $storeId = 1;
        // Create a filter query that returns a term filter instead of range
        $filters = ['price' => $this->createMockFilterQuery('100')];
        $params = [];

        $this->handler->process($params, $filters, $storeId);

        $this->assertEquals([], $params);
        $this->assertCount(0, $filters, 'Price filter should be burned down even if not a range');
    }

    public function testGetRangeFilterWithValidRange(): void
    {
        $filters = ['price' => $this->createRangeFilterQuery(10.00, 50.00)];

        $result = $this->invokeMethod($this->handler, 'getRangeFilter', [&$filters, 'price']);

        $this->assertInstanceOf(Range::class, $result);
        $this->assertEquals(10.00, $result->getFrom());
        $this->assertEquals(50.00, $result->getTo());
        $this->assertCount(0, $filters, 'Filter should be removed');
    }

    public function testGetRangeFilterWithMissingKey(): void
    {
        $otherQuery = $this->createMock(RequestQueryInterface::class);
        $filters = ['other' => $otherQuery];

        $result = $this->invokeMethod($this->handler, 'getRangeFilter', [&$filters, 'price']);

        $this->assertNull($result);
        $this->assertCount(1, $filters, 'Other filters should remain');
    }

    public function testGetRangeFilterWithRemoveFalse(): void
    {
        $filters = ['price' => $this->createRangeFilterQuery(10.00, 50.00)];

        $result = $this->invokeMethod($this->handler, 'getRangeFilter', [&$filters, 'price', false]);

        $this->assertInstanceOf(Range::class, $result);
        $this->assertCount(1, $filters, 'Filter should NOT be removed when remove=false');
    }

    public function testProcessWithHighPriceRange(): void
    {
        $storeId = 1;
        $filters = ['price' => $this->createRangeFilterQuery(1000.00, 50000.00)];
        $params = [];

        $this->priceKeyResolver->method('getPriceKey')->with($storeId)->willReturn('.USD.default');

        $this->handler->process($params, $filters, $storeId);

        $expectedPriceFacet = QueryParamBuilder::FACET_PARAM_PRICE . '.USD.default';
        $this->assertEquals([
            'numericFilters' => [
                sprintf('%s>=%.3f', $expectedPriceFacet, 1000.00),
                sprintf('%s<=%.3f', $expectedPriceFacet, 50000.00)
            ]
        ], $params);
    }

    /**
     * Helper method to create a range filter query for price testing
     */
    private function createRangeFilterQuery(float $from, float $to): MockObject
    {
        $filterQuery = $this->createMock(FilterQuery::class);
        $filterQuery->method('getType')->willReturn(RequestQueryInterface::TYPE_FILTER);
        $filterQuery->method('getReferenceType')->willReturn(FilterQuery::REFERENCE_FILTER);

        $rangeFilter = $this->createMock(Range::class);
        $rangeFilter->method('getType')->willReturn(RequestFilterInterface::TYPE_RANGE);
        $rangeFilter->method('getFrom')->willReturn($from);
        $rangeFilter->method('getTo')->willReturn($to);

        $filterQuery->method('getReference')->willReturn($rangeFilter);

        return $filterQuery;
    }
}

