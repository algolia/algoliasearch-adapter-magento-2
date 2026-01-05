<?php

namespace Algolia\SearchAdapter\Test\Unit\Model\Response;

use Algolia\SearchAdapter\Api\Data\SearchQueryResultInterface;
use Algolia\SearchAdapter\Api\Data\SearchQueryResultInterfaceFactory;
use Algolia\SearchAdapter\Model\Response\SearchQueryResultFactory;
use Algolia\AlgoliaSearch\Test\TestCase;
use PHPUnit\Framework\MockObject\MockObject;

class SearchQueryResultFactoryTest extends TestCase
{
    private ?SearchQueryResultFactory $searchQueryResultFactory = null;
    private null|(SearchQueryResultInterfaceFactory&MockObject) $searchQueryResultInterfaceFactory = null;
    private null|(SearchQueryResultInterface&MockObject) $searchQueryResult = null;

    protected function setUp(): void
    {
        $this->searchQueryResultInterfaceFactory = $this->createMock(SearchQueryResultInterfaceFactory::class);
        $this->searchQueryResult = $this->createMock(SearchQueryResultInterface::class);

        $this->searchQueryResultFactory = new SearchQueryResultFactory(
            $this->searchQueryResultInterfaceFactory
        );
    }

    public function testCreateWithEmptyResponse(): void
    {
        $response = [];

        $this->searchQueryResultInterfaceFactory
            ->expects($this->once())
            ->method('create')
            ->with([
                'hits'        => [],
                'facets'      => [],
                'totalHits'   => 0,
                'totalPages'  => null,
                'hitsPerPage' => null,
                'page'        => 0,
            ])
            ->willReturn($this->searchQueryResult);

        $result = $this->searchQueryResultFactory->create($response);

        $this->assertSame($this->searchQueryResult, $result);
    }

    public function testCreateWithEmptyResults(): void
    {
        $response = ['results' => []];

        $this->searchQueryResultInterfaceFactory
            ->expects($this->once())
            ->method('create')
            ->with([
                'hits'        => [],
                'facets'      => [],
                'totalHits'   => 0,
                'totalPages'  => null,
                'hitsPerPage' => null,
                'page'        => 0,
            ])
            ->willReturn($this->searchQueryResult);

        $result = $this->searchQueryResultFactory->create($response);

        $this->assertSame($this->searchQueryResult, $result);
    }

    public function testCreateWithCompleteResponse(): void
    {
        $response = [
            'results' => [
                [
                    'hits' => [
                        ['objectID' => 'product_123'],
                        ['objectID' => 'product_456'],
                    ],
                    'facets' => [
                        'category' => [
                            'Clothing' => 5,
                            'Electronics' => 3,
                        ],
                        'color' => [
                            'red' => 2,
                            'blue' => 4,
                        ],
                    ],
                    'nbHits' => 2,
                    'nbPages' => 1,
                    'hitsPerPage' => 20,
                    'page' => 1,
                ]
            ]
        ];

        $this->searchQueryResultInterfaceFactory
            ->expects($this->once())
            ->method('create')
            ->with([
                'hits'        => [
                    ['objectID' => 'product_123'],
                    ['objectID' => 'product_456'],
                ],
                'facets'      => [
                    'category' => [
                        'Clothing' => 5,
                        'Electronics' => 3,
                    ],
                    'color' => [
                        'red' => 2,
                        'blue' => 4,
                    ],
                ],
                'totalHits'   => 2,
                'totalPages'  => 1,
                'hitsPerPage' => 20,
                'page'        => 1,
            ])
            ->willReturn($this->searchQueryResult);

        $result = $this->searchQueryResultFactory->create($response);

        $this->assertSame($this->searchQueryResult, $result);
    }

    public function testCreateWithPartialResponse(): void
    {
        $response = [
            'results' => [
                [
                    'hits' => [
                        ['objectID' => 'product_789'],
                    ],
                    'nbHits' => 1,
                ]
            ]
        ];

        $this->searchQueryResultInterfaceFactory
            ->expects($this->once())
            ->method('create')
            ->with([
                'hits'        => [
                    ['objectID' => 'product_789'],
                ],
                'facets'      => [],
                'totalHits'   => 1,
                'totalPages'  => null,
                'hitsPerPage' => null,
                'page'        => 0,
            ])
            ->willReturn($this->searchQueryResult);

        $result = $this->searchQueryResultFactory->create($response);

        $this->assertSame($this->searchQueryResult, $result);
    }

    public function testCreateExtractsFirstResultFromMultiQuery(): void
    {
        $response = [
            'results' => [
                [
                    'hits' => [
                        ['objectID' => 'product_first'],
                    ],
                    'nbHits' => 1,
                ],
                [
                    'hits' => [
                        ['objectID' => 'product_second'],
                    ],
                    'nbHits' => 1,
                ],
            ]
        ];

        $this->searchQueryResultInterfaceFactory
            ->expects($this->once())
            ->method('create')
            ->with($this->callback(function ($data) {
                return $data['hits'][0]['objectID'] === 'product_first';
            }))
            ->willReturn($this->searchQueryResult);

        $result = $this->searchQueryResultFactory->create($response);

        $this->assertSame($this->searchQueryResult, $result);
    }

    public function testCreateWithMissingHitsField(): void
    {
        $response = [
            'results' => [
                [
                    'nbHits' => 10,
                    'nbPages' => 2,
                ]
            ]
        ];

        $this->searchQueryResultInterfaceFactory
            ->expects($this->once())
            ->method('create')
            ->with($this->callback(function ($data) {
                return $data['hits'] === []
                    && $data['totalHits'] === 10
                    && $data['totalPages'] === 2;
            }))
            ->willReturn($this->searchQueryResult);

        $result = $this->searchQueryResultFactory->create($response);

        $this->assertSame($this->searchQueryResult, $result);
    }
}

