<?php

namespace Algolia\SearchAdapter\Test\Unit\Plugin\Model\ResourceModel\Product;

use Algolia\AlgoliaSearch\Test\TestCase;
use Algolia\SearchAdapter\Helper\ConfigHelper;
use Algolia\SearchAdapter\Plugin\Model\ResourceModel\Product\CollectionPlugin;
use Magento\Catalog\Model\Category;
use Magento\Catalog\Model\ResourceModel\Product\Collection as ProductCollection;
use PHPUnit\Framework\MockObject\MockObject;

class CollectionPluginTest extends TestCase
{
    private ?CollectionPlugin $plugin = null;
    private null|(ConfigHelper&MockObject) $configHelper = null;
    private null|(ProductCollection&MockObject) $subject = null;
    private null|(Category&MockObject) $category = null;

    protected function setUp(): void
    {
        $this->configHelper = $this->createMock(ConfigHelper::class);
        $this->subject = $this->createMock(ProductCollection::class);
        $this->category = $this->createMock(Category::class);

        $this->plugin = new CollectionPlugin($this->configHelper);
    }

    public function testAroundAddCategoryFilterUsesAlgoliaFilterWhenEngineSelected(): void
    {
        $categoryId = 42;

        $this->category
            ->expects($this->once())
            ->method('getId')
            ->willReturn($categoryId);

        $this->configHelper
            ->expects($this->once())
            ->method('isAlgoliaEngineSelected')
            ->willReturn(true);

        $this->subject
            ->expects($this->once())
            ->method('addFieldToFilter')
            ->with('category_ids', $categoryId);

        $result = $this->plugin->aroundAddCategoryFilter(
            $this->subject,
            fn() => $this->fail('Proceed should not be called when Algolia engine is selected'),
            $this->category
        );

        $this->assertSame($this->subject, $result);
    }

    public function testAroundAddCategoryFilterCallsProceedWhenAlgoliaNotSelected(): void
    {
        $expectedResult = $this->subject;

        $this->configHelper
            ->expects($this->once())
            ->method('isAlgoliaEngineSelected')
            ->willReturn(false);

        $this->subject
            ->expects($this->never())
            ->method('addFieldToFilter');

        $this->category
            ->expects($this->never())
            ->method('getId');

        $proceedCalled = false;
        $proceed = function (Category $cat) use (&$proceedCalled, $expectedResult) {
            $proceedCalled = true;
            $this->assertSame($this->category, $cat);
            return $expectedResult;
        };

        $result = $this->plugin->aroundAddCategoryFilter(
            $this->subject,
            $proceed,
            $this->category
        );

        $this->assertTrue($proceedCalled, 'Proceed callback should have been called');
        $this->assertSame($expectedResult, $result);
    }

    public function testAroundAddCategoryFilterReturnsSubjectWhenAlgoliaSelected(): void
    {
        $this->category->method('getId')->willReturn(1);
        $this->configHelper->method('isAlgoliaEngineSelected')->willReturn(true);

        $result = $this->plugin->aroundAddCategoryFilter(
            $this->subject,
            fn() => $this->fail('Proceed should not be called'),
            $this->category
        );

        $this->assertInstanceOf(ProductCollection::class, $result);
    }

    public function testAroundAddCategoryFilterWithStringCategoryId(): void
    {
        $categoryId = 'Men~Bottoms';

        $this->category
            ->expects($this->once())
            ->method('getId')
            ->willReturn($categoryId);

        $this->configHelper
            ->method('isAlgoliaEngineSelected')
            ->willReturn(true);

        $this->subject
            ->expects($this->once())
            ->method('addFieldToFilter')
            ->with('category_ids', 'Men~Bottoms');

        $result = $this->plugin->aroundAddCategoryFilter(
            $this->subject,
            fn() => $this->fail('Proceed should not be called'),
            $this->category
        );

        $this->assertSame($this->subject, $result);
    }
}

