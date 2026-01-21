<?php

namespace Algolia\SearchAdapter\Test\Unit\Plugin\Service;

use Algolia\AlgoliaSearch\Service\RenderingManager;
use Algolia\AlgoliaSearch\Test\TestCase;
use Algolia\SearchAdapter\Plugin\Service\RenderingManagerPlugin;
use Algolia\SearchAdapter\Service\BackendRenderingResolver;
use PHPUnit\Framework\MockObject\MockObject;

class RenderingManagerPluginTest extends TestCase
{
    private ?RenderingManagerPlugin $plugin = null;
    private null|(BackendRenderingResolver&MockObject) $backendRenderingResolver = null;
    private null|(RenderingManager&MockObject) $subject = null;

    protected function setUp(): void
    {
        $this->backendRenderingResolver = $this->createMock(BackendRenderingResolver::class);
        $this->subject = $this->createMock(RenderingManager::class);

        $this->plugin = new RenderingManagerPlugin($this->backendRenderingResolver);
    }

    public function testAfterShouldPreventBackendRenderingReturnsFalseWhenCoreReturnsFalse(): void
    {
        $actionName = 'catalog_category_view';
        $storeId = 1;

        $this->backendRenderingResolver
            ->expects($this->never())
            ->method('shouldPreventRendering');

        $result = $this->plugin->afterShouldPreventBackendRendering(
            $this->subject,
            false,
            $actionName,
            $storeId
        );

        $this->assertFalse($result);
    }

    public function testAfterShouldPreventBackendRenderingDelegatesToResolverWhenCoreReturnsTrue(): void
    {
        $actionName = 'catalog_category_view';
        $storeId = 1;

        $this->backendRenderingResolver
            ->expects($this->once())
            ->method('shouldPreventRendering')
            ->with($storeId)
            ->willReturn(true);

        $result = $this->plugin->afterShouldPreventBackendRendering(
            $this->subject,
            true,
            $actionName,
            $storeId
        );

        $this->assertTrue($result);
    }

    public function testAfterShouldPreventBackendRenderingReturnsFalseWhenResolverReturnsFalse(): void
    {
        $actionName = 'catalogsearch_result_index';
        $storeId = 2;

        $this->backendRenderingResolver
            ->expects($this->once())
            ->method('shouldPreventRendering')
            ->with($storeId)
            ->willReturn(false);

        $result = $this->plugin->afterShouldPreventBackendRendering(
            $this->subject,
            true,
            $actionName,
            $storeId
        );

        $this->assertFalse($result);
    }

    /**
     * @dataProvider storeIdDataProvider
     */
    public function testAfterShouldPreventBackendRenderingPassesCorrectStoreId(int $storeId): void
    {
        $actionName = 'catalog_category_view';

        $this->backendRenderingResolver
            ->expects($this->once())
            ->method('shouldPreventRendering')
            ->with($storeId)
            ->willReturn(true);

        $this->plugin->afterShouldPreventBackendRendering(
            $this->subject,
            true,
            $actionName,
            $storeId
        );
    }

    public static function storeIdDataProvider(): array
    {
        return [
            'store 1' => ['storeId' => 1],
            'store 2' => ['storeId' => 2],
            'store 5' => ['storeId' => 5],
            'store 10' => ['storeId' => 10],
        ];
    }
}

