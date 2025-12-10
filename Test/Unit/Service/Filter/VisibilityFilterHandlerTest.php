<?php

namespace Algolia\SearchAdapter\Test\Unit\Service\Filter;

use Algolia\AlgoliaSearch\Api\Product\ProductRecordFieldsInterface;
use Algolia\AlgoliaSearch\Test\TestCase;
use Algolia\SearchAdapter\Service\Filter\VisibilityFilterHandler;
use Algolia\SearchAdapter\Test\Traits\QueryTestTrait;
use Magento\Catalog\Model\Product\Visibility;
use Magento\Framework\Search\Request\QueryInterface as RequestQueryInterface;

class VisibilityFilterHandlerTest extends TestCase
{
    use QueryTestTrait;

    private VisibilityFilterHandler $handler;

    protected function setUp(): void
    {
        $this->handler = new VisibilityFilterHandler();
    }

    public function testProcessWithVisibilityInSearchOnly(): void
    {
        $filters = [
            'visibility' => $this->createMockFilterQuery(Visibility::VISIBILITY_IN_SEARCH)
        ];
        $params = [];

        $this->handler->process($params, $filters);

        $this->assertEquals([
            'numericFilters' => [sprintf('%s=1', ProductRecordFieldsInterface::VISIBILITY_SEARCH)]
        ], $params);
        $this->assertCount(0, $filters, 'Filters should burn down correctly');
    }

    public function testProcessWithVisibilityInCatalogOnly(): void
    {
        $filters = [
            'visibility' => $this->createMockFilterQuery(Visibility::VISIBILITY_IN_CATALOG)
        ];
        $params = [];

        $this->handler->process($params, $filters);

        $this->assertEquals([
            'numericFilters' => [sprintf('%s=1', ProductRecordFieldsInterface::VISIBILITY_CATALOG)]
        ], $params);
        $this->assertCount(0, $filters, 'Filters should burn down correctly');
    }

    public function testProcessWithVisibilityBothValues(): void
    {
        $filterQuery = $this->createMockFilterQuery([
            Visibility::VISIBILITY_IN_SEARCH,
            Visibility::VISIBILITY_IN_CATALOG
        ]);
        $filters = ['visibility' => $filterQuery];
        $params = [];

        $this->handler->process($params, $filters);

        $this->assertEquals([
            'numericFilters' => [
                sprintf('%s=1', ProductRecordFieldsInterface::VISIBILITY_SEARCH),
                sprintf('%s=1', ProductRecordFieldsInterface::VISIBILITY_CATALOG)
            ]
        ], $params);
        $this->assertCount(0, $filters, 'Filters should burn down correctly');
    }

    public function testProcessWithoutVisibilityFilter(): void
    {
        $otherQuery = $this->createMock(RequestQueryInterface::class);
        $filters = ['other' => $otherQuery];
        $params = [];

        $this->handler->process($params, $filters);

        $this->assertEquals([], $params);
        $this->assertCount(1, $filters, 'Non-matching filters should remain');
    }

    public function testProcessWithFalseVisibilityValue(): void
    {
        $filters = ['visibility' => $this->createMockFilterQuery(false)];
        $params = [];

        $this->handler->process($params, $filters);

        $this->assertEquals([], $params);
        $this->assertCount(0, $filters, 'Filters should burn down correctly');
    }

    public function testProcessWithNonMatchingVisibilityValues(): void
    {
        $filterQuery = $this->createMockFilterQuery(1);
        $filters = ['visibility' => $filterQuery];
        $params = [];

        $this->handler->process($params, $filters);

        $this->assertEquals([], $params);
        $this->assertCount(0, $filters, 'Filters should burn down correctly');
    }

    public function testProcessWithExistingNumericFilters(): void
    {
        $filters = [
            'visibility' => $this->createMockFilterQuery(Visibility::VISIBILITY_IN_CATALOG)
        ];
        $params = ['numericFilters' => ['price>100']];

        $this->handler->process($params, $filters);

        $this->assertEquals([
            'numericFilters' => [
                'price>100',
                sprintf('%s=1', ProductRecordFieldsInterface::VISIBILITY_CATALOG)
            ]
        ], $params);
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
        $filters = [
            'visibility' => $this->createMockFilterQuery(Visibility::VISIBILITY_IN_SEARCH)
        ];
        $params = [];

        // Visibility filter doesn't use storeId, but it should still work when passed
        $this->handler->process($params, $filters, 5);

        $this->assertEquals([
            'numericFilters' => [sprintf('%s=1', ProductRecordFieldsInterface::VISIBILITY_SEARCH)]
        ], $params);
    }

    public function testProcessWithVisibilityBothAsScalar(): void
    {
        // Test VISIBILITY_BOTH (4) which contains both search and catalog
        $filters = [
            'visibility' => $this->createMockFilterQuery(Visibility::VISIBILITY_BOTH)
        ];
        $params = [];

        $this->handler->process($params, $filters);

        // VISIBILITY_BOTH (4) is not equal to VISIBILITY_IN_SEARCH (3) or VISIBILITY_IN_CATALOG (2)
        // so no filters should be applied
        // The reason for this is Magento sends the "both" filter on both search and category PLP
        // But Algolia accounts for this during indexing
        // See \Algolia\AlgoliaSearch\Service\Product\RecordBuilder::addVisibilityAttributes
        $this->assertEquals([], $params);
        $this->assertCount(0, $filters, 'Filters should burn down correctly');
    }
}

