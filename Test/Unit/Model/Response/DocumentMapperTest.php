<?php

namespace Algolia\SearchAdapter\Test\Unit\Model\Response;

use Algolia\SearchAdapter\Model\Response\DocumentMapper;
use Algolia\SearchAdapter\Api\Data\DocumentMapperResultInterface;
use Algolia\SearchAdapter\Api\Data\DocumentMapperResultInterfaceFactory;
use Algolia\SearchAdapter\Api\Data\PaginationInfoInterface;
use Algolia\SearchAdapter\Api\Data\SearchQueryResultInterface;
use Algolia\AlgoliaSearch\Test\TestCase;
use PHPUnit\Framework\MockObject\MockObject;

class DocumentMapperTest extends TestCase
{
    private DocumentMapper $documentMapper;
    private DocumentMapperResultInterfaceFactory|MockObject $documentMapperResultFactory;
    private DocumentMapperResultInterface|MockObject $documentMapperResult;
    private PaginationInfoInterface|MockObject $pagination;
    private SearchQueryResultInterface|MockObject $searchQueryResult;

    protected function setUp(): void
    {
        $this->documentMapperResultFactory = $this->createMock(DocumentMapperResultInterfaceFactory::class);
        $this->documentMapperResult = $this->createMock(DocumentMapperResultInterface::class);
        $this->pagination = $this->createMock(PaginationInfoInterface::class);
        $this->searchQueryResult = $this->createMock(SearchQueryResultInterface::class);

        $this->documentMapper = new DocumentMapper(
            $this->documentMapperResultFactory
        );
    }

    public function testBuildDocumentsWithEmptyHits(): void
    {
        $hits = [];
        $result = $this->invokeMethod($this->documentMapper, 'buildDocuments', [$hits]);

        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    public function testBuildDocumentsWithSingleHit(): void
    {
        $hits = [
            ['objectID' => 'product_123']
        ];

        $result = $this->invokeMethod($this->documentMapper, 'buildDocuments', [$hits]);

        $this->assertCount(1, $result);
        $this->assertEquals([
            'fields' => [
                '_id' => ['product_123']
            ],
            'score' => null,
            'sort' => [1, 'product_123']
        ], $result[0]);
    }

    public function testBuildDocumentsWithMultipleHits(): void
    {
        $hits = [
            ['objectID' => 'product_123'],
            ['objectID' => 'product_456'],
            ['objectID' => 'product_789']
        ];

        $result = $this->invokeMethod($this->documentMapper, 'buildDocuments', [$hits]);

        $this->assertCount(3, $result);

        // Check first document
        $this->assertEquals([
            'fields' => [
                '_id' => ['product_123']
            ],
            'score' => null,
            'sort' => [1, 'product_123']
        ], $result[0]);

        // Check second document
        $this->assertEquals([
            'fields' => [
                '_id' => ['product_456']
            ],
            'score' => null,
            'sort' => [2, 'product_456']
        ], $result[1]);

        // Check third document
        $this->assertEquals([
            'fields' => [
                '_id' => ['product_789']
            ],
            'score' => null,
            'sort' => [3, 'product_789']
        ], $result[2]);
    }

    public function testProcessWithEmptyResponse(): void
    {
        $this->searchQueryResult->method('getHits')->willReturn([]);
        $this->searchQueryResult->method('getTotalHits')->willReturn(0);
        $this->searchQueryResult->method('getTotalPages')->willReturn(null);
        $this->searchQueryResult->method('getHitsPerPage')->willReturn(null);
        
        $this->pagination->method('getPageNumber')->willReturn(1);
        $this->pagination->method('getPageSize')->willReturn(20);

        $this->documentMapperResultFactory
            ->expects($this->once())
            ->method('create')
            ->with([
                'documents' => [],
                'totalCount' => 0,
                'totalPages' => 0,
                'pageSize' => 20,
                'currentPage' => 1
            ])
            ->willReturn($this->documentMapperResult);

        $result = $this->documentMapper->process($this->searchQueryResult, $this->pagination);

        $this->assertSame($this->documentMapperResult, $result);
    }

    public function testProcessWithEmptyResults(): void
    {
        $this->searchQueryResult->method('getHits')->willReturn([]);
        $this->searchQueryResult->method('getTotalHits')->willReturn(0);
        $this->searchQueryResult->method('getTotalPages')->willReturn(null);
        $this->searchQueryResult->method('getHitsPerPage')->willReturn(null);
        
        $this->pagination->method('getPageNumber')->willReturn(1);
        $this->pagination->method('getPageSize')->willReturn(20);

        $this->documentMapperResultFactory
            ->expects($this->once())
            ->method('create')
            ->with([
                'documents' => [],
                'totalCount' => 0,
                'totalPages' => 0,
                'pageSize' => 20,
                'currentPage' => 1
            ])
            ->willReturn($this->documentMapperResult);

        $result = $this->documentMapper->process($this->searchQueryResult, $this->pagination);

        $this->assertSame($this->documentMapperResult, $result);
    }

    public function testProcessWithSingleHit(): void
    {
        $hits = [['objectID' => 'product_123']];
        
        $this->searchQueryResult->method('getHits')->willReturn($hits);
        $this->searchQueryResult->method('getTotalHits')->willReturn(1);
        $this->searchQueryResult->method('getTotalPages')->willReturn(1);
        $this->searchQueryResult->method('getHitsPerPage')->willReturn(20);
        
        $this->pagination->method('getPageNumber')->willReturn(1);
        $this->pagination->method('getPageSize')->willReturn(20);

        $this->documentMapperResultFactory
            ->expects($this->once())
            ->method('create')
            ->with($this->callback(function ($data) {
                return $data['totalCount'] === 1
                    && $data['totalPages'] === 1
                    && $data['pageSize'] === 20
                    && $data['currentPage'] === 1
                    && count($data['documents']) === 1
                    && $data['documents'][0]['fields']['_id'][0] === 'product_123';
            }))
            ->willReturn($this->documentMapperResult);

        $result = $this->documentMapper->process($this->searchQueryResult, $this->pagination);

        $this->assertSame($this->documentMapperResult, $result);
    }

    public function testProcessWithMultipleHits(): void
    {
        $hits = [
            ['objectID' => 'product_123'],
            ['objectID' => 'product_456']
        ];
        
        $this->searchQueryResult->method('getHits')->willReturn($hits);
        $this->searchQueryResult->method('getTotalHits')->willReturn(2);
        $this->searchQueryResult->method('getTotalPages')->willReturn(1);
        $this->searchQueryResult->method('getHitsPerPage')->willReturn(20);
        
        $this->pagination->method('getPageNumber')->willReturn(1);
        $this->pagination->method('getPageSize')->willReturn(20);

        $this->documentMapperResultFactory
            ->expects($this->once())
            ->method('create')
            ->with($this->callback(function ($data) {
                return $data['totalCount'] === 2
                    && count($data['documents']) === 2
                    && $data['documents'][0]['fields']['_id'][0] === 'product_123'
                    && $data['documents'][1]['fields']['_id'][0] === 'product_456';
            }))
            ->willReturn($this->documentMapperResult);

        $result = $this->documentMapper->process($this->searchQueryResult, $this->pagination);

        $this->assertSame($this->documentMapperResult, $result);
    }

    public function testProcessWithMultiplePages(): void
    {
        $hits = [
            ['objectID' => 'product_19'],
            ['objectID' => 'product_20'],
            ['objectID' => 'product_21'],
            ['objectID' => 'product_22'],
            ['objectID' => 'product_23'],
            ['objectID' => 'product_24'],
            ['objectID' => 'product_25'],
            ['objectID' => 'product_26'],
            ['objectID' => 'product_27'],
        ];
        
        $this->searchQueryResult->method('getHits')->willReturn($hits);
        $this->searchQueryResult->method('getTotalHits')->willReturn(32);
        $this->searchQueryResult->method('getTotalPages')->willReturn(4);
        $this->searchQueryResult->method('getHitsPerPage')->willReturn(9);
        
        $this->pagination->method('getPageNumber')->willReturn(3);
        $this->pagination->method('getPageSize')->willReturn(9);

        $this->documentMapperResultFactory
            ->expects($this->once())
            ->method('create')
            ->with($this->callback(function ($data) {
                return $data['totalCount'] === 32
                    && count($data['documents']) === 9
                    && $data['totalPages'] === 4
                    && $data['pageSize'] === 9
                    && $data['currentPage'] === 3
                    && $data['documents'][0]['fields']['_id'][0] === 'product_19'
                    && $data['documents'][8]['fields']['_id'][0] === 'product_27';
            }))
            ->willReturn($this->documentMapperResult);

        $result = $this->documentMapper->process($this->searchQueryResult, $this->pagination);

        $this->assertSame($this->documentMapperResult, $result);
    }

    public function testProcessWithMissingPaginationFields(): void
    {
        $hits = [['objectID' => 'product_123']];
        
        $this->searchQueryResult->method('getHits')->willReturn($hits);
        $this->searchQueryResult->method('getTotalHits')->willReturn(1);
        $this->searchQueryResult->method('getTotalPages')->willReturn(null);
        $this->searchQueryResult->method('getHitsPerPage')->willReturn(null);
        
        $this->pagination->method('getPageNumber')->willReturn(2);
        $this->pagination->method('getPageSize')->willReturn(10);

        $this->documentMapperResultFactory
            ->expects($this->once())
            ->method('create')
            ->with($this->callback(function ($data) {
                return $data['totalCount'] === 1 
                    && $data['totalPages'] === 1  // should fallback to pagination
                    && $data['pageSize'] === 10  // should fallback to pagination
                    && $data['currentPage'] === 2
                    && count($data['documents']) === 1
                    && $data['documents'][0]['fields']['_id'][0] === 'product_123';
            }))
            ->willReturn($this->documentMapperResult);

        $result = $this->documentMapper->process($this->searchQueryResult, $this->pagination);

        $this->assertSame($this->documentMapperResult, $result);
    }

    public function testBuildDocumentsSortOrderIncrement(): void
    {
        $hits = [
            ['objectID' => 'product_1'],
            ['objectID' => 'product_2'],
            ['objectID' => 'product_3']
        ];

        $result = $this->invokeMethod($this->documentMapper, 'buildDocuments', [$hits]);

        $this->assertCount(3, $result);
        $this->assertEquals(1, $result[0]['sort'][0]);
        $this->assertEquals(2, $result[1]['sort'][0]);
        $this->assertEquals(3, $result[2]['sort'][0]);
    }
}
