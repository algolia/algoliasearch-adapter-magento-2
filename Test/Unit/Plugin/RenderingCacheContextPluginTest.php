<?php

namespace Algolia\SearchAdapter\Test\Unit\Plugin;

use Algolia\AlgoliaSearch\Helper\Configuration\InstantSearchHelper;
use Algolia\AlgoliaSearch\Test\TestCase;
use Algolia\SearchAdapter\Plugin\RenderingCacheContextPlugin;
use Algolia\SearchAdapter\Service\BackendRenderingResolver;
use Magento\Framework\App\Http\Context as HttpContext;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Model\StoreManagerInterface;

class RenderingCacheContextPluginTest extends TestCase
{
    protected ?RenderingCacheContextPlugin $plugin;
    protected ?BackendRenderingResolver $backendRenderingResolver;

    protected ?InstantSearchHelper $instantSearchHelper;
    protected ?StoreManagerInterface $storeManager;

    protected function setUp(): void
    {
        $this->backendRenderingResolver = $this->createMock(BackendRenderingResolver::class);
        $this->instantSearchHelper = $this->createMock(InstantSearchHelper::class);
        $this->storeManager = $this->createMock(StoreManagerInterface::class);

        $this->plugin = new RenderingCacheContextPlugin(
            $this->backendRenderingResolver,
            $this->instantSearchHelper,
            $this->storeManager
        );
    }

    protected function getStoreMock(): StoreInterface
    {
        $store = $this->createMock(StoreInterface::class);
        $store->method('getId')->willReturn(1);
        return $store;
    }

    public function testAfterGetDataAddsRenderingContextNoBackendRender(): void
    {
        $this->backendRenderingResolver->method('shouldPreventRendering')->willReturn(true);
        $this->storeManager->method('getStore')->willReturn($this->getStoreMock());

        $this->instantSearchHelper->method('shouldReplaceCategories')->willReturn(true);

        $result = $this->plugin->afterGetData(
            $this->createMock(HttpContext::class),
            []
        );

        $this->assertArrayHasKey(RenderingCacheContextPlugin::RENDERING_CONTEXT, $result);
        $this->assertEquals(
            RenderingCacheContextPlugin::RENDERING_WITHOUT_BACKEND,
            $result[RenderingCacheContextPlugin::RENDERING_CONTEXT]
        );
    }

    public function testAfterGetDataAddsRenderingContextWithBackendRender(): void
    {
        $this->backendRenderingResolver->method('shouldPreventRendering')->willReturn(false);
        $this->storeManager->method('getStore')->willReturn($this->getStoreMock());

        $this->instantSearchHelper->method('shouldReplaceCategories')->willReturn(true);

        $result = $this->plugin->afterGetData(
            $this->createMock(HttpContext::class),
            []
        );

        $this->assertArrayHasKey(RenderingCacheContextPlugin::RENDERING_CONTEXT, $result);
        $this->assertEquals(
            RenderingCacheContextPlugin::RENDERING_WITH_BACKEND,
            $result[RenderingCacheContextPlugin::RENDERING_CONTEXT]
        );
    }

    public function testAfterGetDataDoesNotModifyDataIfNotApplicable(): void
    {
        $subject = $this->createMock(HttpContext::class);

        $this->backendRenderingResolver->method('shouldPreventRendering')->willReturn(false);
        $this->storeManager->method('getStore')->willReturn($this->getStoreMock());

        $this->instantSearchHelper->method('shouldReplaceCategories')->willReturn(false);

        $data = ['existing_key' => 'existing_value'];
        $result = $this->plugin->afterGetData($subject, $data);

        $this->assertEquals($data, $result);
    }

    /**
     * @throws \ReflectionException
     */
    public function testShouldApplyCacheContext(): void
    {
        $this->storeManager->method('getStore')->willReturn($this->getStoreMock());

        $this->instantSearchHelper->method('shouldReplaceCategories')->willReturn(true);

        $this->assertTrue($this->invokeMethod($this->plugin, 'shouldApplyCacheContext'));
    }
}
