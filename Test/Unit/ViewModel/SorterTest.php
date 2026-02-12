<?php

namespace Algolia\SearchAdapter\Test\Unit\ViewModel;

use Algolia\AlgoliaSearch\Helper\Configuration\InstantSearchHelper;
use Algolia\AlgoliaSearch\Test\TestCase;
use Algolia\SearchAdapter\ViewModel\Sorter;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Model\StoreManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;

class SorterTest extends TestCase
{
    private ?Sorter $sorter = null;
    private null|(StoreManagerInterface&MockObject) $storeManager = null;
    private null|(InstantSearchHelper&MockObject) $instantSearchHelper = null;
    private null|(StoreInterface&MockObject) $store = null;

    protected function setUp(): void
    {
        $this->storeManager = $this->createMock(StoreManagerInterface::class);
        $this->instantSearchHelper = $this->createMock(InstantSearchHelper::class);
        $this->store = $this->createMock(StoreInterface::class);

        $this->storeManager->method('getStore')->willReturn($this->store);
        $this->store->method('getId')->willReturn(1);

        $this->sorter = new Sorter(
            $this->storeManager,
            $this->instantSearchHelper
        );
    }

    public function testGetSortParamDelimiter(): void
    {
        $this->assertEquals('~', $this->sorter->getSortParamDelimiter());
    }

    public function testSortParamDelimiterConstant(): void
    {
        $this->assertEquals('~', Sorter::SORT_PARAM_DELIMITER);
    }

    public function testSortParamDefaultConstant(): void
    {
        $this->assertEquals('relevance', Sorter::SORT_PARAM_DEFAULT);
    }

    public function testGetSortingOptionsIncludesRelevanceAsFirst(): void
    {
        $this->instantSearchHelper
            ->method('getSorting')
            ->with(1)
            ->willReturn([]);

        $result = $this->sorter->getSortingOptions();

        $this->assertIsArray($result);
        $this->assertNotEmpty($result);
        $this->assertEquals('relevance', $result[0]['key']);
        $this->assertEquals(__('Relevance'), $result[0]['label']);
    }

    public function testGetSortingOptionsWithNoConfiguredSorting(): void
    {
        $this->instantSearchHelper
            ->method('getSorting')
            ->with(1)
            ->willReturn([]);

        $result = $this->sorter->getSortingOptions();

        $this->assertCount(1, $result);
        $this->assertEquals('relevance', $result[0]['key']);
    }

    public function testGetSortingOptionsTransformsSortingOptions(): void
    {
        // All relevant data points in sorting config array
        $sortingConfig = [
            [
                'attribute' => 'price',
                'sort' => 'asc',
                'sortLabel' => 'Price: Low to High'
            ],
            [
                'attribute' => 'price',
                'sort' => 'desc',
                'sortLabel' => 'Price: High to Low'
            ],
            [
                'attribute' => 'name',
                'sort' => 'asc',
                'sortLabel' => 'Name: A to Z'
            ],
            [
                'attribute' => 'created_at',
                'sort' => 'desc',
                'sortLabel' => 'Newest First'
            ],
        ];

        $this->instantSearchHelper
            ->method('getSorting')
            ->with(1)
            ->willReturn($sortingConfig);

        $result = $this->sorter->getSortingOptions();

        $this->assertCount(5, $result);

        $this->assertEquals('relevance', $result[0]['key']);

        $this->assertEquals('price~asc', $result[1]['key']);
        $this->assertEquals('Price: Low to High', $result[1]['label']);

        $this->assertEquals('price~desc', $result[2]['key']);
        $this->assertEquals('Price: High to Low', $result[2]['label']);

        $this->assertEquals('name~asc', $result[3]['key']);
        $this->assertEquals('Name: A to Z', $result[3]['label']);

        $this->assertEquals('created_at~desc', $result[4]['key']);
        $this->assertEquals('Newest First', $result[4]['label']);
    }

    public function testGetSortingOptionsUsesCorrectStoreId(): void
    {
        $store = $this->createMock(StoreInterface::class);
        $store->method('getId')->willReturn(5);

        $storeManager = $this->createMock(StoreManagerInterface::class);
        $storeManager->method('getStore')->willReturn($store);

        $this->instantSearchHelper
            ->expects($this->once())
            ->method('getSorting')
            ->with(5)
            ->willReturn([]);

        $sorter = new Sorter($storeManager, $this->instantSearchHelper);
        $sorter->getSortingOptions();
    }

    public function testTransformSortingOptionsReturnsIndexedArray(): void
    {
        $sortingConfig = [
            0 => ['attribute' => 'price', 'sort' => 'asc', 'sortLabel' => 'Price'],
            5 => ['attribute' => 'name', 'sort' => 'desc', 'sortLabel' => 'Name'],
        ];

        $this->instantSearchHelper
            ->method('getSorting')
            ->willReturn($sortingConfig);

        $result = $this->sorter->getSortingOptions();

        $this->assertArrayHasKey(0, $result);
        $this->assertArrayHasKey(1, $result);
        $this->assertArrayHasKey(2, $result);
        $this->assertArrayNotHasKey(5, $result);
    }

    public function testGetSortingOptionsWithSpecialCharactersInLabel(): void
    {
        $sortingConfig = [
            [
                'attribute' => 'price',
                'sort' => 'asc',
                'sortLabel' => 'Price (USD) - Low → High'
            ],
        ];

        $this->instantSearchHelper
            ->method('getSorting')
            ->willReturn($sortingConfig);

        $result = $this->sorter->getSortingOptions();

        $this->assertEquals('Price (USD) - Low → High', $result[1]['label']);
    }

    public function testGetSortingOptionsWithAttributeContainingUnderscore(): void
    {
        $sortingConfig = [
            [
                'attribute' => 'created_at',
                'sort' => 'desc',
                'sortLabel' => 'Date Created'
            ],
        ];

        $this->instantSearchHelper
            ->method('getSorting')
            ->willReturn($sortingConfig);

        $result = $this->sorter->getSortingOptions();

        $this->assertEquals('created_at~desc', $result[1]['key']);
    }

    public function testSorterImplementsArgumentInterface(): void
    {
        $this->assertInstanceOf(
            \Magento\Framework\View\Element\Block\ArgumentInterface::class,
            $this->sorter
        );
    }
}

