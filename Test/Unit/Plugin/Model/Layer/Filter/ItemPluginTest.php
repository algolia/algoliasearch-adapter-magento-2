<?php

namespace Algolia\SearchAdapter\Test\Unit\Plugin\Model\Layer\Filter;

use Algolia\AlgoliaSearch\Service\Category\CategoryPathProvider;
use Algolia\AlgoliaSearch\Service\Product\PriceKeyResolver;
use Algolia\AlgoliaSearch\Test\TestCase;
use Algolia\SearchAdapter\Helper\ConfigHelper;
use Algolia\SearchAdapter\Plugin\Model\Layer\Filter\ItemPlugin;
use Magento\Catalog\Model\Layer\Filter\AbstractFilter;
use Magento\Catalog\Model\Layer\Filter\Item;
use Magento\Framework\UrlInterface;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Theme\Block\Html\Pager;
use PHPUnit\Framework\MockObject\MockObject;

class ItemPluginTest extends TestCase
{
    private ?ItemPlugin $plugin = null;
    private null|(ConfigHelper&MockObject) $configHelper = null;
    private null|(StoreManagerInterface&MockObject) $storeManager = null;
    private null|(CategoryPathProvider&MockObject) $categoryPathProvider = null;
    private null|(PriceKeyResolver&MockObject) $priceKeyResolver = null;
    private null|(UrlInterface&MockObject) $urlBuilder = null;
    private null|(Pager&MockObject) $pager = null;
    private null|(StoreInterface&MockObject) $store = null;

    protected function setUp(): void
    {
        $this->configHelper = $this->createMock(ConfigHelper::class);
        $this->storeManager = $this->createMock(StoreManagerInterface::class);
        $this->categoryPathProvider = $this->createMock(CategoryPathProvider::class);
        $this->priceKeyResolver = $this->createMock(PriceKeyResolver::class);
        $this->urlBuilder = $this->createMock(UrlInterface::class);
        $this->pager = $this->createMock(Pager::class);
        $this->store = $this->createMock(StoreInterface::class);

        $this->storeManager->method('getStore')->willReturn($this->store);
        $this->store->method('getId')->willReturn(1);

        $this->plugin = new ItemPlugin(
            $this->configHelper,
            $this->storeManager,
            $this->categoryPathProvider,
            $this->priceKeyResolver,
            $this->urlBuilder,
            $this->pager
        );
    }

    public function testAfterGetUrlReturnOriginalWhenSeoFiltersDisabled(): void
    {
        $originalUrl = 'https://example.com/original-url';
        $item = $this->createMockItem('brand', 'Nike');

        $this->configHelper
            ->expects($this->once())
            ->method('areSeoFiltersEnabled')
            ->with(1)
            ->willReturn(false);

        $this->urlBuilder
            ->expects($this->never())
            ->method('getUrl');

        $result = $this->plugin->afterGetUrl($item, $originalUrl);

        $this->assertEquals($originalUrl, $result);
    }

    public function testAfterGetUrlBuildsCategoryUrl(): void
    {
        $originalUrl = 'https://example.com/original-url?cat=15';
        $expectedUrl = 'https://example.com/original-url?cat=Men~Tops';
        $item = $this->createMockCategoryItem('cat', '15');

        $this->configHelper
            ->method('areSeoFiltersEnabled')
            ->willReturn(true);

        $this->categoryPathProvider
            ->expects($this->once())
            ->method('getCategoryPageId')
            ->with('15', 1, '~')
            ->willReturn('Men~Tops');

        $this->pager
            ->method('getPageVarName')
            ->willReturn('p');

        $this->urlBuilder   
            ->expects($this->once())
            ->method('getUrl')
            ->with(
                '*/*/*',
                [
                    '_current' => true,
                    '_use_rewrite' => true,
                    '_query' => [
                        'cat' => 'Men~Tops',
                        'p' => null
                    ]
                ]
            )
            ->willReturn($expectedUrl);

        $result = $this->plugin->afterGetUrl($item, $originalUrl);

        $this->assertEquals($expectedUrl, $result);
    }

    public function testAfterGetUrlBuildsPriceUrlWithPriceKeyParam(): void
    {
        $originalUrl = 'https://example.com/original-url?price.USD.default=%3A20';
        $expectedUrl = 'https://example.com/original-url?price=100-200';
        $item = $this->createMockPriceItem('price', '100-200');

        $this->configHelper
            ->method('areSeoFiltersEnabled')
            ->willReturn(true);

        $this->priceKeyResolver
            ->expects($this->once())
            ->method('getPriceKey')
            ->with(1)
            ->willReturn('.USD.default');

        $this->pager
            ->method('getPageVarName')
            ->willReturn('p');

        $this->urlBuilder
            ->expects($this->once())
            ->method('getUrl')
            ->with(
                '*/*/*',
                [
                    '_current' => true,
                    '_use_rewrite' => true,
                    '_query' => [
                        'price' => '100-200',
                        'p' => null,
                        'price_USD_default' => null
                    ]
                ]
            )
            ->willReturn($expectedUrl);

        $result = $this->plugin->afterGetUrl($item, $originalUrl);

        $this->assertEquals($expectedUrl, $result);
    }

    public function testAfterGetUrlBuildsUrlWithLabelWhenSeoFiltersEnabled(): void
    {
        $originalUrl = 'https://example.com/original-url?style_general=116';
        $expectedUrl = 'https://example.com/original-url?style_general=Insulated';
        $item = $this->createMockItem('style_general', 'Insulated');

        $this->configHelper
            ->method('areSeoFiltersEnabled')
            ->willReturn(true);

        $this->pager
            ->method('getPageVarName')
            ->willReturn('p');

        $this->urlBuilder
            ->expects($this->once())
            ->method('getUrl')
            ->with(
                '*/*/*',
                [
                    '_current' => true,
                    '_use_rewrite' => true,
                    '_query' => [
                        'style_general' => 'Insulated',
                        'p' => null
                    ]
                ]
            )
            ->willReturn($expectedUrl);

        $result = $this->plugin->afterGetUrl($item, $originalUrl);

        $this->assertEquals($expectedUrl, $result);
    }

    public function testAfterGetUrlUsesLabelAsSlug(): void
    {
        $originalUrl = 'https://example.com/url?style_general=120';
        $expectedUrl = 'https://example.com/url?style_general=Tank';
        $item = $this->createMockItem('style_general', 'Tank');

        $this->configHelper
            ->method('areSeoFiltersEnabled')
            ->willReturn(true);

        $this->pager
            ->method('getPageVarName')
            ->willReturn('p');

        $this->urlBuilder
            ->expects($this->once())
            ->method('getUrl')
            ->with(
                '*/*/*',
                $this->callback(function ($params) {
                    return $params['_query']['style_general'] === 'Tank';
                })
            )
            ->willReturn($expectedUrl);

        $result = $this->plugin->afterGetUrl($item, $originalUrl);
        $this->assertEquals($expectedUrl, $result);
    }

    public function testAfterGetUrlWithDifferentStoreId(): void
    {
        $store = $this->createMock(StoreInterface::class);
        $store->method('getId')->willReturn(5);

        $storeManager = $this->createMock(StoreManagerInterface::class);
        $storeManager->method('getStore')->willReturn($store);

        $plugin = new ItemPlugin(
            $this->configHelper,
            $storeManager,
            $this->categoryPathProvider,
            $this->priceKeyResolver,
            $this->urlBuilder,
            $this->pager
        );

        $item = $this->createMockItem('brand', 'Nike');

        $this->configHelper
            ->expects($this->once())
            ->method('areSeoFiltersEnabled')
            ->with(5)
            ->willReturn(false);

        $result = $plugin->afterGetUrl($item, 'original-url');

        $this->assertEquals('original-url', $result);
    }

    public function testGetAttributeSlugReturnsItemLabel(): void
    {
        $item = $this->createMock(Item::class);
        $item->method('getData')
            ->with('label')
            ->willReturn('Test Label');

        $result = $this->invokeMethod($this->plugin, 'getAttributeSlug', [$item]);

        $this->assertEquals('Test Label', $result);
    }

    public function testGetPriceParamNameReplacesDotsWithUnderscores(): void
    {
        $this->priceKeyResolver
            ->expects($this->once())
            ->method('getPriceKey')
            ->with(1)
            ->willReturn('.USD.default');

        $result = $this->invokeMethod($this->plugin, 'getPriceParamName', [1]);

        $this->assertEquals('price_USD_default', $result);
    }

    public function testGetPriceParamNameWithCustomerGroup(): void
    {
        $this->priceKeyResolver
            ->expects($this->once())
            ->method('getPriceKey')
            ->with(1)
            ->willReturn('.EUR.group_2');

        $result = $this->invokeMethod($this->plugin, 'getPriceParamName', [1]);

        $this->assertEquals('price_EUR_group_2', $result);
    }

    public function testAfterGetUrlWithSpecialCharactersInLabel(): void
    {
        $originalUrl = 'https://example.com/url?brand=12';
        $expectedUrl = 'https://example.com/url?brand=Nike+%26+Adidas';
        $item = $this->createMockItem('brand', 'Nike & Adidas');

        $this->configHelper
            ->method('areSeoFiltersEnabled')
            ->willReturn(true);

        $this->pager
            ->method('getPageVarName')
            ->willReturn('p');

        $this->urlBuilder
            ->expects($this->once())
            ->method('getUrl')
            ->with(
                '*/*/*',
                $this->callback(function ($params) {
                    return $params['_query']['brand'] === 'Nike & Adidas';
                })
            )
            ->willReturn($expectedUrl);

        $this->plugin->afterGetUrl($item, $originalUrl);
    }

    private function createMockItem(string $requestVar, string $label): Item&MockObject
    {
        $filter = $this->createMock(AbstractFilter::class);
        $filter->method('getRequestVar')->willReturn($requestVar);

        $item = $this->createMock(Item::class);
        $item->method('getFilter')->willReturn($filter);
        $item->method('getData')
            ->with('label')
            ->willReturn($label);

        return $item;
    }

    private function createMockCategoryItem(string $requestVar, string $valueString): Item&MockObject
    {
        $filter = $this->createMock(AbstractFilter::class);
        $filter->method('getRequestVar')->willReturn($requestVar);

        $item = $this->createMock(Item::class);
        $item->method('getFilter')->willReturn($filter);
        $item->method('getValueString')->willReturn($valueString);

        return $item;
    }

    private function createMockPriceItem(string $requestVar, string $valueString): Item&MockObject
    {
        $filter = $this->createMock(AbstractFilter::class);
        $filter->method('getRequestVar')->willReturn($requestVar);

        $item = $this->createMock(Item::class);
        $item->method('getFilter')->willReturn($filter);
        $item->method('getValueString')->willReturn($valueString);

        return $item;
    }
}

