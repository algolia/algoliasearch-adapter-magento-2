<?php

namespace Algolia\SearchAdapter\Test\Unit\Service;

use Algolia\AlgoliaSearch\Test\TestCase;
use Algolia\SearchAdapter\Service\StoreIdResolver;
use Magento\Framework\App\ScopeInterface;
use Magento\Framework\App\ScopeResolverInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Search\Request\Dimension;
use Magento\Framework\Search\RequestInterface;
use PHPUnit\Framework\MockObject\MockObject;

class StoreIdResolverTest extends TestCase
{
    private StoreIdResolver $storeIdResolver;
    private ScopeResolverInterface|MockObject $scopeResolver;

    protected function setUp(): void
    {
        $this->scopeResolver = $this->createMock(ScopeResolverInterface::class);

        $this->storeIdResolver = new StoreIdResolver($this->scopeResolver);
    }

    /**
     * @dataProvider multiStoreDataProvider
     */
    public function testGetStoreId(int $storeId): void
    {
        $request = $this->createMockRequestWithStore($storeId);
        $result = $this->storeIdResolver->getStoreId($request);
        $this->assertEquals($storeId, $result, "Failed for store ID: $storeId");
    }
    
    public function testGetStoreIdWithInvalidStore(): void
    {
        $request = $this->createMock(RequestInterface::class);
        $dimension = $this->createMock(Dimension::class);

        $request->method('getDimensions')->willReturn([$dimension]);
        $dimension->method('getValue')->willReturn('invalid-store');

        $this->scopeResolver->method('getScope')
            ->with('invalid-store')
            ->willThrowException(new NoSuchEntityException(__('Invalid scope')));

        $this->expectException(NoSuchEntityException::class);
        $this->storeIdResolver->getStoreId($request);
    }

    private function createMockRequestWithStore(int $storeId): RequestInterface|MockObject
    {
        $request = $this->createMock(RequestInterface::class);
        $dimension = $this->createMockDimension($storeId);
        $request->method('getDimensions')->willReturn([$dimension]);
        return $request;
    }

    private function createMockDimension(int $storeId = 1): Dimension|MockObject
    {
        $dimension = $this->createMock(Dimension::class);
        $dimension->method('getValue')->willReturn((string) $storeId);
        
        $this->scopeResolver->method('getScope')->willReturnCallback(function($scopeId) {
            $scope = $this->createMock(ScopeInterface::class);
            $scope->method('getId')->willReturn((int) $scopeId);
            return $scope;
        });
        
        return $dimension;
    }

    public static function multiStoreDataProvider(): array
    {
        return [
            [ 'storeId' => 1 ], 
            [ 'storeId' => 3 ], 
            [ 'storeId' => 7 ], 
            [ 'storeId' => 10 ], 
            [ 'storeId' => 100 ]
        ];
    }
}

