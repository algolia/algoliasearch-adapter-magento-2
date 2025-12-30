<?php

namespace Algolia\SearchAdapter\Test\Unit\Plugin\Block\LayeredNavigation;

use Algolia\AlgoliaSearch\Test\TestCase;
use Algolia\SearchAdapter\Helper\ConfigHelper;
use Algolia\SearchAdapter\Plugin\Block\LayeredNavigation\RenderedLayeredPlugin;
use Algolia\SearchAdapter\Service\FacetValueConverter;
use Magento\Framework\UrlInterface;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Swatches\Block\LayeredNavigation\RenderLayered;
use Magento\Theme\Block\Html\Pager;
use PHPUnit\Framework\MockObject\MockObject;

class RenderedLayeredPluginTest extends TestCase
{
    private ?RenderedLayeredPlugin $plugin = null;
    private null|(ConfigHelper&MockObject) $configHelper = null;
    private null|(StoreManagerInterface&MockObject) $storeManager = null;
    private null|(FacetValueConverter&MockObject) $facetValueConverter = null;
    private null|(UrlInterface&MockObject) $urlBuilder = null;
    private null|(Pager&MockObject) $pager = null;
    private null|(StoreInterface&MockObject) $store = null;
    private null|(RenderLayered&MockObject) $subject = null;

    protected function setUp(): void
    {
        $this->configHelper = $this->createMock(ConfigHelper::class);
        $this->storeManager = $this->createMock(StoreManagerInterface::class);
        $this->facetValueConverter = $this->createMock(FacetValueConverter::class);
        $this->urlBuilder = $this->createMock(UrlInterface::class);
        $this->pager = $this->createMock(Pager::class);
        $this->store = $this->createMock(StoreInterface::class);
        $this->subject = $this->createMock(RenderLayered::class);

        $this->storeManager->method('getStore')->willReturn($this->store);
        $this->store->method('getId')->willReturn(1);

        $this->plugin = new RenderedLayeredPlugin(
            $this->configHelper,
            $this->storeManager,
            $this->facetValueConverter,
            $this->urlBuilder,
            $this->pager
        );
    }

    public function testAfterBuildUrlReturnOriginalWhenSeoFiltersDisabled(): void
    {
        $originalUrl = 'https://example.com/original-url?color=49';
        $attributeCode = 'color';
        $optionId = 49;

        $this->configHelper
            ->expects($this->once())
            ->method('areSeoFiltersEnabled')
            ->with(1)
            ->willReturn(false);

        $this->facetValueConverter
            ->expects($this->never())
            ->method('convertOptionIdToLabel');

        $result = $this->plugin->afterBuildUrl($this->subject, $originalUrl, $attributeCode, $optionId);

        $this->assertEquals($originalUrl, $result);
    }

    public function testAfterBuildUrlConvertsOptionIdToLabelWhenSeoFiltersEnabled(): void
    {
        $originalUrl = 'https://example.com/original-url?color=49';
        $expectedUrl = 'https://example.com/original-url?color=Red';
        $attributeCode = 'color';
        $optionId = 49;
        $label = 'Red';

        $this->configHelper
            ->method('areSeoFiltersEnabled')
            ->willReturn(true);

        $this->facetValueConverter
            ->expects($this->once())
            ->method('convertOptionIdToLabel')
            ->with($attributeCode, $optionId)
            ->willReturn($label);

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
                        'color' => 'Red',
                        'p' => null
                    ]
                ]
            )
            ->willReturn($expectedUrl);

        $result = $this->plugin->afterBuildUrl($this->subject, $originalUrl, $attributeCode, $optionId);

        $this->assertEquals($expectedUrl, $result);
    }

    public function testAfterBuildUrlWithDifferentStoreId(): void
    {
        $store = $this->createMock(StoreInterface::class);
        $store->method('getId')->willReturn(3);

        $storeManager = $this->createMock(StoreManagerInterface::class);
        $storeManager->method('getStore')->willReturn($store);

        $plugin = new RenderedLayeredPlugin(
            $this->configHelper,
            $storeManager,
            $this->facetValueConverter,
            $this->urlBuilder,
            $this->pager
        );

        $this->configHelper
            ->expects($this->once())
            ->method('areSeoFiltersEnabled')
            ->with(3)
            ->willReturn(false);

        $result = $plugin->afterBuildUrl($this->subject, 'original-url', 'size', 50);

        $this->assertEquals('original-url', $result);
    }

    public function testAfterBuildUrlWithSizeAttribute(): void
    {
        $originalUrl = 'https://example.com/original-url?size=50';
        $expectedUrl = 'https://example.com/original-url?size=Large';
        $attributeCode = 'size';
        $optionId = 50;
        $label = 'Large';

        $this->configHelper
            ->method('areSeoFiltersEnabled')
            ->willReturn(true);

        $this->facetValueConverter
            ->expects($this->once())
            ->method('convertOptionIdToLabel')
            ->with($attributeCode, $optionId)
            ->willReturn($label);

        $this->pager
            ->method('getPageVarName')
            ->willReturn('p');

        $this->urlBuilder
            ->expects($this->once())
            ->method('getUrl')
            ->with(
                '*/*/*',
                $this->callback(function ($params) {
                    return $params['_query']['size'] === 'Large';
                })
            )
            ->willReturn($expectedUrl);

        $result = $this->plugin->afterBuildUrl($this->subject, $originalUrl, $attributeCode, $optionId);

        $this->assertEquals($expectedUrl, $result);
    }

    public function testAfterBuildUrlResetsPagination(): void
    {
        $pageVarName = 'page';

        $this->configHelper
            ->method('areSeoFiltersEnabled')
            ->willReturn(true);

        $this->facetValueConverter
            ->method('convertOptionIdToLabel')
            ->willReturn('Label');

        $this->pager
            ->expects($this->once())
            ->method('getPageVarName')
            ->willReturn($pageVarName);

        $this->urlBuilder
            ->expects($this->once())
            ->method('getUrl')
            ->with(
                '*/*/*',
                $this->callback(function ($params) use ($pageVarName) {
                    return array_key_exists($pageVarName, $params['_query'])
                        && $params['_query'][$pageVarName] === null;
                })
            )
            ->willReturn('https://example.com/url');

        $this->plugin->afterBuildUrl($this->subject, 'original', 'color', 49);
    }

    public function testAfterBuildUrlWithEmptyLabelFromConverter(): void
    {
        $attributeCode = 'brand';
        $optionId = 100;

        $this->configHelper
            ->method('areSeoFiltersEnabled')
            ->willReturn(true);

        $this->facetValueConverter
            ->expects($this->once())
            ->method('convertOptionIdToLabel')
            ->with($attributeCode, $optionId)
            ->willReturn('');

        $this->pager
            ->method('getPageVarName')
            ->willReturn('p');

        $this->urlBuilder
            ->expects($this->never())
            ->method('getUrl');

        $result = $this->plugin->afterBuildUrl($this->subject, 'original', $attributeCode, $optionId);
        $this->assertEquals('original', $result);
    }

    public function testAfterBuildUrlWithSpecialCharactersInLabel(): void
    {
        $originalUrl = 'https://example.com/url?brand=101';
        $expectedUrl = 'https://example.com/url?brand=Nike+%26+Adidas';

        $attributeCode = 'brand';
        $optionId = 101;
        $label = 'Nike & Adidas';

        $this->configHelper
            ->method('areSeoFiltersEnabled')
            ->willReturn(true);

        $this->facetValueConverter
            ->method('convertOptionIdToLabel')
            ->willReturn($label);

        $this->pager
            ->method('getPageVarName')
            ->willReturn('p');

        $this->urlBuilder
            ->expects($this->once())
            ->method('getUrl')
            ->with(
                '*/*/*',
                $this->callback(function ($params) use ($label) {
                    return $params['_query']['brand'] === $label;
                })
            )
            ->willReturn($expectedUrl);

        $result = $this->plugin->afterBuildUrl($this->subject, $originalUrl, $attributeCode, $optionId);
        $this->assertEquals($expectedUrl, $result);
    }

    public function testAfterBuildUrlUsesCurrentAndRewriteOptions(): void
    {
        $this->configHelper
            ->method('areSeoFiltersEnabled')
            ->willReturn(true);

        $this->facetValueConverter
            ->method('convertOptionIdToLabel')
            ->willReturn('Label');

        $this->pager
            ->method('getPageVarName')
            ->willReturn('p');

        $this->urlBuilder
            ->expects($this->once())
            ->method('getUrl')
            ->with(
                '*/*/*',
                $this->callback(function ($params) {
                    return $params['_current'] === true && $params['_use_rewrite'] === true;
                })
            )
            ->willReturn('https://example.com/url');

        $this->plugin->afterBuildUrl($this->subject, 'original', 'color', 49);
    }
}

