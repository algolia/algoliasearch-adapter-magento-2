<?php

namespace Algolia\SearchAdapter\Test\Unit\Service\Filter;

use Algolia\AlgoliaSearch\Api\Product\RuleContextInterface;
use Algolia\AlgoliaSearch\Helper\ConfigHelper;
use Algolia\AlgoliaSearch\Service\Category\CategoryPathProvider;
use Algolia\AlgoliaSearch\Test\TestCase;
use Algolia\SearchAdapter\Service\Filter\CategoryFilterHandler;
use Algolia\SearchAdapter\Test\Traits\QueryTestTrait;
use Magento\Catalog\Api\CategoryRepositoryInterface;
use Magento\Catalog\Model\Category;
use Magento\Framework\Search\Request\QueryInterface as RequestQueryInterface;
use PHPUnit\Framework\MockObject\MockObject;

class CategoryFilterHandlerTest extends TestCase
{
    use QueryTestTrait;

    private CategoryFilterHandler $handler;
    private ConfigHelper|MockObject $configHelper;
    private CategoryPathProvider|MockObject $categoryPathProvider;
    private CategoryRepositoryInterface|MockObject $categoryRepository;

    protected function setUp(): void
    {
        $this->configHelper = $this->createMock(ConfigHelper::class);
        $this->categoryPathProvider = $this->createMock(CategoryPathProvider::class);
        $this->categoryRepository = $this->createMock(CategoryRepositoryInterface::class);
        $this->handler = new CategoryFilterHandler($this->configHelper, $this->categoryPathProvider, $this->categoryRepository);
    }

    public function testProcessWithCategoryFilter(): void
    {
        $filters = ['category' => $this->createMockFilterQuery('12')];
        $params = [];

        $this->handler->process($params, $filters);

        $this->assertEquals([
            'facetFilters' => ['categoryIds:12'],
            'ruleContexts' => [RuleContextInterface::MERCH_RULE_CATEGORY_PREFIX . '12'],
        ], $params);
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

        $this->assertEquals([
            'facetFilters' => ['color:Blue', 'categoryIds:42'],
            'ruleContexts' => [RuleContextInterface::MERCH_RULE_CATEGORY_PREFIX . '42'],
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

    public function testProcessWithVisualMerchEnabled(): void
    {
        $storeId = 1;
        $categoryId = '12';
        $categoryPageId = 'Electronics///Computers';
        $attributeName = 'categoryPageId';

        $filters = ['category' => $this->createMockFilterQuery($categoryId)];
        $params = [];

        $this->configHelper->method('isVisualMerchEnabled')
            ->with($storeId)
            ->willReturn(true);

        $this->configHelper->method('getCategoryPageIdAttributeName')
            ->with($storeId)
            ->willReturn($attributeName);

        $category = $this->createMock(Category::class);
        $this->categoryRepository->method('get')
            ->with($categoryId)
            ->willReturn($category);

        $this->categoryPathProvider->method('getCategoryPageId')
            ->with($category, $storeId)
            ->willReturn($categoryPageId);

        $this->handler->process($params, $filters, $storeId);

        $this->assertEquals([
            'facetFilters' => ['categoryIds:12'],
            'ruleContexts' => [RuleContextInterface::MERCH_RULE_CATEGORY_PREFIX . '12'],
            'filters' => 'categoryPageId:"Electronics///Computers"',
        ], $params);
        $this->assertCount(0, $filters, 'Filters should burn down correctly');
    }

    public function testProcessWithVisualMerchDisabled(): void
    {
        $storeId = 1;
        $filters = ['category' => $this->createMockFilterQuery('12')];
        $params = [];

        $this->configHelper->method('isVisualMerchEnabled')
            ->with($storeId)
            ->willReturn(false);

        $this->categoryRepository->expects($this->never())->method('get');

        $this->handler->process($params, $filters, $storeId);

        $this->assertEquals([
            'facetFilters' => ['categoryIds:12'],
            'ruleContexts' => [RuleContextInterface::MERCH_RULE_CATEGORY_PREFIX . '12'],
        ], $params);
        $this->assertArrayNotHasKey('filters', $params);
    }
}

