<?php

namespace Algolia\SearchAdapter\Test\Unit\Model\Response;

use Algolia\SearchAdapter\Model\Response\DocumentMapper;
use Algolia\AlgoliaSearch\Test\TestCase;

class DocumentMapperTest extends TestCase
{
    private DocumentMapper $documentMapper;

    protected function setUp(): void
    {
        $this->documentMapper = new DocumentMapper();
    }

    public function testBuildDocumentsWithEmptyResponse(): void
    {
        $searchResponse = [];
        $result = $this->documentMapper->buildDocuments($searchResponse);

        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    public function testBuildDocumentsWithEmptyResults(): void
    {
        $searchResponse = ['results' => []];
        $result = $this->documentMapper->buildDocuments($searchResponse);

        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    public function testBuildDocumentsWithSingleResult(): void
    {
        $searchResponse = [
            'results' => [
                [
                    'hits' => [
                        ['objectID' => 'product_123']
                    ]
                ]
            ]
        ];

        $result = $this->documentMapper->buildDocuments($searchResponse);

        $this->assertCount(1, $result);
        $this->assertEquals([
            'fields' => [
                '_id' => ['product_123']
            ],
            'score' => null,
            'sort' => [1, 'product_123']
        ], $result[0]);
    }

    public function testBuildDocumentsWithMultipleResults(): void
    {
        $searchResponse = [
            'results' => [
                [
                    'hits' => [
                        ['objectID' => 'product_123'],
                        ['objectID' => 'product_456']
                    ]
                ],
                [
                    'hits' => [
                        ['objectID' => 'product_789']
                    ]
                ]
            ]
        ];

        $result = $this->documentMapper->buildDocuments($searchResponse);

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

    public function testBuildDocumentsWithEmptyHits(): void
    {
        $searchResponse = [
            'results' => [
                [
                    'hits' => []
                ]
            ]
        ];

        $result = $this->documentMapper->buildDocuments($searchResponse);

        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    public function testBuildDocumentsWithMissingHitsKey(): void
    {
        $searchResponse = [
            'results' => [
                [
                    'someOtherKey' => 'value'
                ]
            ]
        ];

        $result = $this->documentMapper->buildDocuments($searchResponse);

        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    public function testExtractHitsWithEmptyResponse(): void
    {
        $searchResponse = [];
        $result = $this->invokeMethod($this->documentMapper, 'extractHits', [$searchResponse]);

        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    public function testExtractHitsWithEmptyResults(): void
    {
        $searchResponse = ['results' => []];
        $result = $this->invokeMethod($this->documentMapper, 'extractHits', [$searchResponse]);

        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    public function testExtractHitsWithSingleResult(): void
    {
        $searchResponse = [
            'results' => [
                [
                    'hits' => [
                        ['objectID' => 'product_123', 'name' => 'Test Product']
                    ]
                ]
            ]
        ];

        $result = $this->invokeMethod($this->documentMapper, 'extractHits', [$searchResponse]);

        $this->assertCount(1, $result);
        $this->assertEquals([
            'objectID' => 'product_123',
            'name' => 'Test Product'
        ], $result[0]);
    }

    public function testExtractHitsWithMultipleResults(): void
    {
        $searchResponse = [
            'results' => [
                [
                    'hits' => [
                        ['objectID' => 'product_123', 'name' => 'Product 1'],
                        ['objectID' => 'product_456', 'name' => 'Product 2']
                    ]
                ],
                [
                    'hits' => [
                        ['objectID' => 'product_789', 'name' => 'Product 3']
                    ]
                ]
            ]
        ];

        $result = $this->invokeMethod($this->documentMapper, 'extractHits', [$searchResponse]);

        $this->assertCount(3, $result);
        $this->assertEquals([
            'objectID' => 'product_123',
            'name' => 'Product 1'
        ], $result[0]);
        $this->assertEquals([
            'objectID' => 'product_456',
            'name' => 'Product 2'
        ], $result[1]);
        $this->assertEquals([
            'objectID' => 'product_789',
            'name' => 'Product 3'
        ], $result[2]);
    }

    public function testExtractHitsWithEmptyHits(): void
    {
        $searchResponse = [
            'results' => [
                [
                    'hits' => []
                ]
            ]
        ];

        $result = $this->invokeMethod($this->documentMapper, 'extractHits', [$searchResponse]);

        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    public function testExtractHitsWithMissingHitsKey(): void
    {
        $searchResponse = [
            'results' => [
                [
                    'someOtherKey' => 'value'
                ]
            ]
        ];

        $result = $this->invokeMethod($this->documentMapper, 'extractHits', [$searchResponse]);

        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    public function testExtractHitsWithNullHits(): void
    {
        $searchResponse = [
            'results' => [
                [
                    'hits' => null
                ]
            ]
        ];

        $result = $this->invokeMethod($this->documentMapper, 'extractHits', [$searchResponse]);

        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    public function testBuildDocumentsSortOrderIncrement(): void
    {
        $searchResponse = [
            'results' => [
                [
                    'hits' => [
                        ['objectID' => 'product_1'],
                        ['objectID' => 'product_2'],
                        ['objectID' => 'product_3']
                    ]
                ]
            ]
        ];

        $result = $this->documentMapper->buildDocuments($searchResponse);

        $this->assertCount(3, $result);
        $this->assertEquals(1, $result[0]['sort'][0]);
        $this->assertEquals(2, $result[1]['sort'][0]);
        $this->assertEquals(3, $result[2]['sort'][0]);
    }
}
