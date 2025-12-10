<?php

namespace Algolia\SearchAdapter\Test\Unit\Service\Filter;

use Algolia\AlgoliaSearch\Test\TestCase;
use Algolia\SearchAdapter\Service\Filter\CategoryFilterHandler;
use Algolia\SearchAdapter\Test\Traits\QueryTestTrait;
use Magento\Framework\Search\Request\QueryInterface as RequestQueryInterface;

class CategoryFilterHandlerTest extends TestCase
{
    use QueryTestTrait;

    private CategoryFilterHandler $handler;

    protected function setUp(): void
    {
        $this->handler = new CategoryFilterHandler();
    }

    public function testProcessWithCategoryFilter(): void
    {
        $filters = ['category' => $this->createMockFilterQuery('12')];
        $params = [];

        $this->handler->process($params, $filters);

        $this->assertEquals(['facetFilters' => ['categoryIds:12']], $params);
        $this->assertCount(0, $filters, 'Filters should burn down correctly');
    }

    public function testProcessWithoutCategoryFilter(): void
    {
        $otherQuery = $this->createMock(RequestQueryInterface::class);
        $filters = ['other' => $otherQuery];
        $params = [];

        $this->handler->process($params, $filters);

        $this->assertEquals([], $params);
        $this->assertCount(1, $filters, 'Non-matching filters should remain');
    }

    public function testProcessWithNonFilterType(): void
    {
        $nonFilterQuery = $this->createMock(RequestQueryInterface::class);
        $nonFilterQuery->method('getType')->willReturn(RequestQueryInterface::TYPE_MATCH);
        $filters = ['category' => $nonFilterQuery];
        $params = [];

        $this->handler->process($params, $filters);

        $this->assertEquals([], $params);
        $this->assertCount(0, $filters, 'Filter key removed but no param applied');
    }

    public function testProcessWithExistingFacetFilters(): void
    {
        $filters = ['category' => $this->createMockFilterQuery('42')];
        $params = ['facetFilters' => ['color:Blue']];

        $this->handler->process($params, $filters);

        $this->assertEquals(['facetFilters' => ['color:Blue', 'categoryIds:42']], $params);
        $this->assertCount(0, $filters, 'Filters should burn down correctly');
    }

    public function testProcessWithEmptyFilters(): void
    {
        $filters = [];
        $params = [];

        $this->handler->process($params, $filters);

        $this->assertEquals([], $params);
        $this->assertCount(0, $filters);
    }

    public function testProcessIgnoresStoreId(): void
    {
        $filters = ['category' => $this->createMockFilterQuery('12')];
        $params = [];

        // Category filter doesn't use storeId, but it should still work when passed
        $this->handler->process($params, $filters, 5);

        $this->assertEquals(['facetFilters' => ['categoryIds:12']], $params);
    }
}

