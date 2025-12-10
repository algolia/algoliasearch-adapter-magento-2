<?php

namespace Algolia\SearchAdapter\Test\Unit\Model\Response;

use Algolia\SearchAdapter\Model\Response\ResponseFactory;
use Algolia\AlgoliaSearch\Test\TestCase;
use Magento\Framework\Api\Search\Document;
use Magento\Framework\Api\Search\DocumentInterface;
use Magento\Framework\ObjectManagerInterface;
use Magento\Framework\Search\Response\Aggregation;
use Magento\Framework\Search\Response\Aggregation\Value;
use Magento\Framework\Search\Response\Bucket;
use Magento\Framework\Search\Response\QueryResponse;
use PHPUnit\Framework\MockObject\MockObject;

class ResponseFactoryTest extends TestCase
{
    private ResponseFactory $responseFactory;
    private ObjectManagerInterface|MockObject $objectManager;

    protected function setUp(): void
    {
        $this->objectManager = $this->createMock(ObjectManagerInterface::class);
        $this->responseFactory = new ResponseFactory($this->objectManager);
    }

    public function testCreateWithEmptyResponse(): void
    {
        $emptyAggregation = $this->createMock(Aggregation::class);
        $queryResponse = $this->createMock(QueryResponse::class);

        $this->objectManager
            ->expects($this->exactly(2))
            ->method('create')
            ->willReturnCallback(function ($class, $args) use ($emptyAggregation, $queryResponse) {
                if ($class === Aggregation::class) {
                    $this->assertEquals(['buckets' => []], $args);
                    return $emptyAggregation;
                }
                if ($class === QueryResponse::class) {
                    $this->assertEquals([], $args['documents']);
                    $this->assertSame($emptyAggregation, $args['aggregations']);
                    $this->assertEquals(0, $args['total']);
                    return $queryResponse;
                }
                $this->fail("Unexpected scenario with class: $class");
            });

        $result = $this->responseFactory->create([]);

        $this->assertSame($queryResponse, $result);
    }

    public function testCreateWithDocumentsOnly(): void
    {
        $response = [
            'documents' => [
                ['_id' => '123'],
                ['_id' => '456'],
            ],
            'total' => 2
        ];

        $emptyAggregation = $this->createMock(Aggregation::class);
        $queryResponse = $this->createMock(QueryResponse::class);

        $this->objectManager
            ->expects($this->exactly(2))
            ->method('create')
            ->willReturnCallback(function ($class, $args) use ($emptyAggregation, $queryResponse) {
                if ($class === Aggregation::class) {
                    return $emptyAggregation;
                }
                if ($class === QueryResponse::class) {
                    $this->assertCount(2, $args['documents']);
                    $this->assertInstanceOf(Document::class, $args['documents'][0]);
                    $this->assertInstanceOf(Document::class, $args['documents'][1]);
                    $this->assertEquals('123', $args['documents'][0]->getId());
                    $this->assertEquals('456', $args['documents'][1]->getId());
                    $this->assertEquals(2, $args['total']);
                    return $queryResponse;
                }
                $this->fail("Unexpected scenario with class: $class");
            });

        $result = $this->responseFactory->create($response);

        $this->assertSame($queryResponse, $result);
    }

    public function testCreateWithAggregationsOnly(): void
    {
        $response = [
            'aggregations' => [
                'color_bucket' => [
                    '49' => ['value' => '49', 'count' => 5],
                    '50' => ['value' => '50', 'count' => 3],
                ],
            ],
            'total' => 0
        ];

        $colorBucket = $this->createMock(Bucket::class);
        $aggregation = $this->createMock(Aggregation::class);
        $queryResponse = $this->createMock(QueryResponse::class);
        $redValue = $this->createMock(Value::class);
        $blueValue = $this->createMock(Value::class);

        $createCallIndex = 0;
        $this->objectManager
            ->expects($this->exactly(5))
            ->method('create')
            ->willReturnCallback(function ($class, $args) use (
                &$createCallIndex, $redValue, $blueValue, $colorBucket, $aggregation, $queryResponse
            ) {
                $createCallIndex++;

                if ($class === Value::class) {
                    if ($args['value'] === '49') {
                        $this->assertEquals(['value' => 49, 'count' => 5], $args['metrics']);
                        return $redValue;
                    }
                    if ($args['value'] === '50') {
                        $this->assertEquals(['value' => 50, 'count' => 3], $args['metrics']);
                        return $blueValue;
                    }
                }

                if ($class === Bucket::class) {
                    $this->assertEquals('color_bucket', $args['name']);
                    $this->assertCount(2, $args['values']);
                    return $colorBucket;
                }

                if ($class === Aggregation::class) {
                    $this->assertArrayHasKey('color_bucket', $args['buckets']);
                    $this->assertSame($colorBucket, $args['buckets']['color_bucket']);
                    return $aggregation;
                }

                if ($class === QueryResponse::class) {
                    $this->assertEquals([], $args['documents']);
                    $this->assertSame($aggregation, $args['aggregations']);
                    $this->assertEquals(0, $args['total']);
                    return $queryResponse;
                }

                $this->fail("Unexpected scenario with class: $class");
            });

        $result = $this->responseFactory->create($response);

        $this->assertSame($queryResponse, $result);
    }

    public function testCreateWithDocumentsAndAggregations(): void
    {
        $response = [
            'documents' => [
                ['_id' => '123'],
            ],
            'aggregations' => [
                'category_bucket' => [
                    '16' => ['value' => '16', 'count' => 10],
                ],
            ],
            'total' => 50
        ];

        $categoryValue = $this->createMock(Value::class);
        $categoryBucket = $this->createMock(Bucket::class);
        $aggregation = $this->createMock(Aggregation::class);
        $queryResponse = $this->createMock(QueryResponse::class);

        $this->objectManager
            ->expects($this->exactly(4))
            ->method('create')
            ->willReturnCallback(function ($class, $args) use (
                $categoryValue, $categoryBucket, $aggregation, $queryResponse
            ) {
                if ($class === Value::class) {
                    return $categoryValue;
                }
                if ($class === Bucket::class) {
                    return $categoryBucket;
                }
                if ($class === Aggregation::class) {
                    return $aggregation;
                }
                if ($class === QueryResponse::class) {
                    $this->assertCount(1, $args['documents']);
                    $this->assertEquals('123', $args['documents'][0]->getId());
                    $this->assertSame($aggregation, $args['aggregations']);
                    $this->assertEquals(50, $args['total']);
                    return $queryResponse;
                }
                $this->fail("Unexpected scenario with class: $class");
            });

        $result = $this->responseFactory->create($response);

        $this->assertSame($queryResponse, $result);
    }

    public function testBuildDocumentsWithDirectId(): void
    {
        $rawDocuments = [
            ['_id' => 'product_123'],
            ['_id' => 'product_456'],
        ];

        $result = $this->invokeMethod($this->responseFactory, 'buildDocuments', [$rawDocuments]);

        $this->assertCount(2, $result);
        $this->assertInstanceOf(Document::class, $result[0]);
        $this->assertInstanceOf(Document::class, $result[1]);
        $this->assertEquals('product_123', $result[0]->getId());
        $this->assertEquals('product_456', $result[1]->getId());
    }

    public function testBuildDocumentsWithNestedFieldsId(): void
    {
        $rawDocuments = [
            ['fields' => ['_id' => ['product_nested_123']]],
            ['fields' => ['_id' => ['product_nested_456']]],
        ];

        $result = $this->invokeMethod($this->responseFactory, 'buildDocuments', [$rawDocuments]);

        $this->assertCount(2, $result);
        $this->assertEquals('product_nested_123', $result[0]->getId());
        $this->assertEquals('product_nested_456', $result[1]->getId());
    }

    public function testBuildDocumentsWithMixedIdFormats(): void
    {
        $rawDocuments = [
            ['_id' => 'direct_id'],
            ['fields' => ['_id' => ['nested_id']]],
        ];

        $result = $this->invokeMethod($this->responseFactory, 'buildDocuments', [$rawDocuments]);

        $this->assertCount(2, $result);
        $this->assertEquals('direct_id', $result[0]->getId());
        $this->assertEquals('nested_id', $result[1]->getId());
    }

    public function testBuildDocumentsWithMissingIdFiltersOutDocument(): void
    {
        $rawDocuments = [
            ['someField' => 'value'],
        ];

        $result = $this->invokeMethod($this->responseFactory, 'buildDocuments', [$rawDocuments]);

        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    public function testBuildDocumentsFiltersOutDocumentsWithoutId(): void
    {
        $rawDocuments = [
            ['_id' => 'valid_id'],
            ['someField' => 'no_id_here'],
            ['fields' => ['_id' => ['nested_valid_id']]],
            ['other' => 'also_no_id'],
        ];

        $result = $this->invokeMethod($this->responseFactory, 'buildDocuments', [$rawDocuments]);

        $this->assertCount(2, $result);
        $this->assertEquals('valid_id', $result[0]->getId());
        $this->assertEquals('nested_valid_id', $result[1]->getId());
    }

    public function testBuildDocumentsWithEmptyArray(): void
    {
        $result = $this->invokeMethod($this->responseFactory, 'buildDocuments', [[]]);

        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    public function testBuildAggregationsWithEmptyArray(): void
    {
        $aggregation = $this->createMock(Aggregation::class);

        $this->objectManager
            ->expects($this->once())
            ->method('create')
            ->with(Aggregation::class, ['buckets' => []])
            ->willReturn($aggregation);

        $result = $this->invokeMethod($this->responseFactory, 'buildAggregations', [[]]);

        $this->assertSame($aggregation, $result);
    }

    public function testBuildAggregationsWithMultipleBuckets(): void
    {
        $rawAggregations = [
            'color_bucket' => [
                '49' => ['value' => '49', 'count' => 5],
            ],
            'size_bucket' => [
                '50' => ['value' => '50', 'count' => 2],
                '51' => ['value' => '51', 'count' => 3],
            ],
        ];

        $colorValue = $this->createMock(Value::class);
        $smallValue = $this->createMock(Value::class);
        $mediumValue = $this->createMock(Value::class);
        $colorBucket = $this->createMock(Bucket::class);
        $sizeBucket = $this->createMock(Bucket::class);
        $aggregation = $this->createMock(Aggregation::class);

        $this->objectManager
            ->expects($this->exactly(6))
            ->method('create')
            ->willReturnCallback(function ($class, $args) use (
                $colorValue, $smallValue, $mediumValue, $colorBucket, $sizeBucket, $aggregation
            ) {
                if ($class === Value::class) {
                    return match ($args['value']) {
                        '49' => $colorValue,
                        '50' => $smallValue,
                        '51' => $mediumValue,
                        default => $this->fail("Unexpected value: {$args['value']}")
                    };
                }
                if ($class === Bucket::class) {
                    return match ($args['name']) {
                        'color_bucket' => $colorBucket,
                        'size_bucket' => $sizeBucket,
                        default => $this->fail("Unexpected bucket: {$args['name']}")
                    };
                }
                if ($class === Aggregation::class) {
                    $this->assertCount(2, $args['buckets']);
                    $this->assertArrayHasKey('color_bucket', $args['buckets']);
                    $this->assertArrayHasKey('size_bucket', $args['buckets']);
                    return $aggregation;
                }
                $this->fail("Unexpected scenario with class: $class");
            });

        $result = $this->invokeMethod($this->responseFactory, 'buildAggregations', [$rawAggregations]);

        $this->assertSame($aggregation, $result);
    }

    public function testPrepareValuesWithEmptyArray(): void
    {
        $result = $this->invokeMethod($this->responseFactory, 'prepareValues', [[]]);

        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    public function testPrepareValuesWithMultipleValues(): void
    {
        $values = [
            'option1' => ['count' => 10],
            'option2' => ['count' => 20, 'other_metric' => 100],
        ];

        $value1 = $this->createMock(Value::class);
        $value2 = $this->createMock(Value::class);

        $this->objectManager
            ->expects($this->exactly(2))
            ->method('create')
            ->willReturnCallback(function ($class, $args) use ($value1, $value2) {
                $this->assertEquals(Value::class, $class);

                if ($args['value'] === 'option1') {
                    $this->assertEquals(['count' => 10], $args['metrics']);
                    return $value1;
                }
                if ($args['value'] === 'option2') {
                    $this->assertEquals(['count' => 20, 'other_metric' => 100], $args['metrics']);
                    return $value2;
                }
                $this->fail("Unexpected value: {$args['value']}");
            });

        $result = $this->invokeMethod($this->responseFactory, 'prepareValues', [$values]);

        $this->assertCount(2, $result);
        $this->assertSame($value1, $result[0]);
        $this->assertSame($value2, $result[1]);
    }

    public function testCreateDefaultsToZeroTotal(): void
    {
        $response = [
            'documents' => [['_id' => 'product_1']],
            'aggregations' => [],
        ];

        $aggregation = $this->createMock(Aggregation::class);
        $queryResponse = $this->createMock(QueryResponse::class);

        $this->objectManager
            ->method('create')
            ->willReturnCallback(function ($class, $args) use ($aggregation, $queryResponse) {
                if ($class === Aggregation::class) {
                    return $aggregation;
                }
                if ($class === QueryResponse::class) {
                    $this->assertEquals(0, $args['total']);
                    return $queryResponse;
                }
                $this->fail("Unexpected class: $class");
            });

        $result = $this->responseFactory->create($response);

        $this->assertSame($queryResponse, $result);
    }

    public function testBuildDocumentsPreservesPriorityOfDirectIdOverNestedId(): void
    {
        $rawDocuments = [
            [
                '_id' => 'direct_takes_priority',
                'fields' => ['_id' => ['nested_ignored']]
            ],
        ];

        $result = $this->invokeMethod($this->responseFactory, 'buildDocuments', [$rawDocuments]);

        $this->assertEquals('direct_takes_priority', $result[0]->getId());
    }
}

