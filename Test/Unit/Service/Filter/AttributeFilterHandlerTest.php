<?php

namespace Algolia\SearchAdapter\Test\Unit\Service\Filter;

use Algolia\AlgoliaSearch\Api\Product\ReplicaManagerInterface;
use Algolia\AlgoliaSearch\Helper\Configuration\InstantSearchHelper;
use Algolia\AlgoliaSearch\Test\TestCase;
use Algolia\SearchAdapter\Service\FacetValueConverter;
use Algolia\SearchAdapter\Service\Filter\AttributeFilterHandler;
use Algolia\SearchAdapter\Test\Traits\QueryTestTrait;
use Magento\Framework\Search\Request\Filter\Term;
use Magento\Framework\Search\Request\FilterInterface as RequestFilterInterface;
use Magento\Framework\Search\Request\Query\Filter as FilterQuery;
use Magento\Framework\Search\Request\QueryInterface as RequestQueryInterface;
use PHPUnit\Framework\MockObject\MockObject;

class AttributeFilterHandlerTest extends TestCase
{
    use QueryTestTrait;

    private ?AttributeFilterHandler $handler = null;
    private null|(InstantSearchHelper&MockObject) $instantSearchHelper = null;
    private null|(FacetValueConverter&MockObject) $facetValueConverter = null;

    protected function setUp(): void
    {
        $this->instantSearchHelper = $this->createMock(InstantSearchHelper::class);
        $this->facetValueConverter = $this->createMock(FacetValueConverter::class);
        $this->handler = new AttributeFilterHandler(
            $this->instantSearchHelper,
            $this->facetValueConverter
        );
    }

    /**
     * @dataProvider facetFiltersDataProvider
     */
    public function testProcess(
        array $filterDefinitions,
        array $configuredFacets,
        array $expectedParams,
        int $expectedRemainingFilters,
        array $facetValueConversions = []
    ): void {
        $storeId = 1;
        $params = [];

        // Build filters from definitions
        $filters = [];
        foreach ($filterDefinitions as $key => $definition) {
            if ($definition['type'] === 'facet') {
                $filters[$key] = $this->createFilterQueryForFacet($definition['field'], $definition['optionId']);
            } elseif ($definition['type'] === 'non-term') {
                $filters[$key] = $this->createNonTermFilter();
            }
        }

        $this->instantSearchHelper->method('getFacets')->with($storeId)->willReturn($configuredFacets);

        // Setup facet value converter expectations
        if (!empty($facetValueConversions)) {
            $valueMap = [];
            foreach ($facetValueConversions as $conversion) {
                $valueMap[] = [$conversion['attribute'], $conversion['optionId'], $conversion['label']];
            }
            $this->facetValueConverter
                ->method('convertOptionIdToLabel')
                ->willReturnMap($valueMap);
        }

        $this->handler->process($params, $filters, $storeId);

        $this->assertEquals($expectedParams, $params);
        $this->assertCount($expectedRemainingFilters, $filters, 'Filters should burn down correctly');
    }

    public static function facetFiltersDataProvider(): array
    {
        return [
            'single facet filter - color' => [
                'filterDefinitions' => [
                    'color' => ['type' => 'facet', 'field' => 'color', 'optionId' => 123]
                ],
                'configuredFacets' => [
                    [ReplicaManagerInterface::SORT_KEY_ATTRIBUTE_NAME => 'color']
                ],
                'expectedParams' => [
                    'facetFilters' => ['color:Blue']
                ],
                'expectedRemainingFilters' => 0,
                'facetValueConversions' => [
                    ['attribute' => 'color', 'optionId' => 123, 'label' => 'Blue']
                ]
            ],
            'multiple facet filters' => [
                'filterDefinitions' => [
                    'color' => ['type' => 'facet', 'field' => 'color', 'optionId' => 123],
                    'size' => ['type' => 'facet', 'field' => 'size', 'optionId' => 456],
                    'style' => ['type' => 'facet', 'field' => 'style', 'optionId' => 789]
                ],
                'configuredFacets' => [
                    [ReplicaManagerInterface::SORT_KEY_ATTRIBUTE_NAME => 'color'],
                    [ReplicaManagerInterface::SORT_KEY_ATTRIBUTE_NAME => 'size'],
                    [ReplicaManagerInterface::SORT_KEY_ATTRIBUTE_NAME => 'style']
                ],
                'expectedParams' => [
                    'facetFilters' => ['color:Blue', 'size:Small', 'style:Basic']
                ],
                'expectedRemainingFilters' => 0,
                'facetValueConversions' => [
                    ['attribute' => 'color', 'optionId' => 123, 'label' => 'Blue'],
                    ['attribute' => 'size', 'optionId' => 456, 'label' => 'Small'],
                    ['attribute' => 'style', 'optionId' => 789, 'label' => 'Basic']
                ]
            ],
            'filter not in configured facets' => [
                'filterDefinitions' => [
                    'color' => ['type' => 'facet', 'field' => 'color', 'optionId' => 123],
                    'material' => ['type' => 'facet', 'field' => 'material', 'optionId' => 999]
                ],
                'configuredFacets' => [
                    [ReplicaManagerInterface::SORT_KEY_ATTRIBUTE_NAME => 'color']
                ],
                'expectedParams' => [
                    'facetFilters' => ['color:Blue']
                ],
                'expectedRemainingFilters' => 1,
                'facetValueConversions' => [
                    ['attribute' => 'color', 'optionId' => 123, 'label' => 'Blue']
                ]
            ],
            'empty filters array' => [
                'filterDefinitions' => [],
                'configuredFacets' => [
                    [ReplicaManagerInterface::SORT_KEY_ATTRIBUTE_NAME => 'color']
                ],
                'expectedParams' => [],
                'expectedRemainingFilters' => 0,
                'facetValueConversions' => []
            ],
            'no configured facets' => [
                'filterDefinitions' => [
                    'color' => ['type' => 'facet', 'field' => 'color', 'optionId' => 123]
                ],
                'configuredFacets' => [],
                'expectedParams' => [],
                'expectedRemainingFilters' => 1,
                'facetValueConversions' => []
            ],
            'mixed valid and non-term filters' => [
                'filterDefinitions' => [
                    'color' => ['type' => 'facet', 'field' => 'color', 'optionId' => 123],
                    'other' => ['type' => 'non-term']
                ],
                'configuredFacets' => [
                    [ReplicaManagerInterface::SORT_KEY_ATTRIBUTE_NAME => 'color']
                ],
                'expectedParams' => [
                    'facetFilters' => ['color:Blue']
                ],
                'expectedRemainingFilters' => 1,
                'facetValueConversions' => [
                    ['attribute' => 'color', 'optionId' => 123, 'label' => 'Blue']
                ]
            ]
        ];
    }

    public function testProcessWithExistingFacetFilters(): void
    {
        $storeId = 1;
        $params = [
            'facetFilters' => ['categoryIds:12']
        ];

        $filters = [
            'color' => $this->createFilterQueryForFacet('color', 123)
        ];

        $this->instantSearchHelper->method('getFacets')->with($storeId)->willReturn([
            [ReplicaManagerInterface::SORT_KEY_ATTRIBUTE_NAME => 'color']
        ]);

        $this->facetValueConverter
            ->method('convertOptionIdToLabel')
            ->with('color', 123)
            ->willReturn('Blue');

        $this->handler->process($params, $filters, $storeId);

        $this->assertEquals([
            'facetFilters' => ['categoryIds:12', 'color:Blue']
        ], $params);
        $this->assertCount(0, $filters);
    }

    public function testGetFacetFilterTermWithValidFilter(): void
    {
        $filter = $this->createFilterQueryForFacet('color', 123);

        $result = $this->invokeMethod($this->handler, 'getFacetFilterTerm', [$filter]);

        $this->assertInstanceOf(Term::class, $result);
        $this->assertEquals('color', $result->getField());
        $this->assertEquals(123, $result->getValue());
    }

    public function testGetFacetFilterTermWithNonFilterType(): void
    {
        $filter = $this->createMock(RequestQueryInterface::class);
        $filter->method('getType')->willReturn(RequestQueryInterface::TYPE_MATCH);

        $result = $this->invokeMethod($this->handler, 'getFacetFilterTerm', [$filter]);

        $this->assertNull($result);
    }

    public function testGetFacetFilterTermWithNonTermReference(): void
    {
        $filterQuery = $this->createMock(FilterQuery::class);
        $filterQuery->method('getType')->willReturn(RequestQueryInterface::TYPE_FILTER);

        $filterQuery->method('getReference')->willReturn($this->createNonTermFilter());

        $result = $this->invokeMethod($this->handler, 'getFacetFilterTerm', [$filterQuery]);

        $this->assertNull($result);
    }

    public function testGetMatchedFacetFound(): void
    {
        $facets = [
            [ReplicaManagerInterface::SORT_KEY_ATTRIBUTE_NAME => 'color', 'label' => 'Color'],
            [ReplicaManagerInterface::SORT_KEY_ATTRIBUTE_NAME => 'size', 'label' => 'Size']
        ];

        $result = $this->invokeMethod($this->handler, 'getMatchedFacet', [&$facets, 'color']);

        $this->assertEquals([ReplicaManagerInterface::SORT_KEY_ATTRIBUTE_NAME => 'color', 'label' => 'Color'], $result);
        $this->assertCount(1, $facets, 'Matched facet should be removed from array');
    }

    public function testGetMatchedFacetNotFound(): void
    {
        $facets = [
            [ReplicaManagerInterface::SORT_KEY_ATTRIBUTE_NAME => 'color', 'label' => 'Color']
        ];

        $result = $this->invokeMethod($this->handler, 'getMatchedFacet', [&$facets, 'size']);

        $this->assertNull($result);
        $this->assertCount(1, $facets, 'Facets array should remain unchanged');
    }

    public function testGetMatchedFacetWithRemoveFalse(): void
    {
        $facets = [
            [ReplicaManagerInterface::SORT_KEY_ATTRIBUTE_NAME => 'color', 'label' => 'Color']
        ];

        $result = $this->invokeMethod($this->handler, 'getMatchedFacet', [&$facets, 'color', false]);

        $this->assertEquals([ReplicaManagerInterface::SORT_KEY_ATTRIBUTE_NAME => 'color', 'label' => 'Color'], $result);
        $this->assertCount(1, $facets, 'Facets array should NOT be modified when remove=false');
    }

    /**
     * Helper method to create a properly structured filter query for facet testing
     */
    private function createFilterQueryForFacet(string $field, int $optionId): MockObject
    {
        $filterQuery = $this->createMock(FilterQuery::class);
        $filterQuery->method('getType')->willReturn(RequestQueryInterface::TYPE_FILTER);

        $termFilter = $this->createMock(Term::class);
        $termFilter->method('getType')->willReturn(RequestFilterInterface::TYPE_TERM);
        $termFilter->method('getField')->willReturn($field);
        $termFilter->method('getValue')->willReturn($optionId);

        $filterQuery->method('getReference')->willReturn($termFilter);

        return $filterQuery;
    }

    /**
     * Helper method to create a non-term filter for testing
     */
    private function createNonTermFilter(): MockObject
    {
        $filter = $this->createMock(RequestQueryInterface::class);
        $filter->method('getType')->willReturn(RequestQueryInterface::TYPE_MATCH);
        return $filter;
    }
}

