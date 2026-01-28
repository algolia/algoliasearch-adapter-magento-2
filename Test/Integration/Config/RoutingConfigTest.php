<?php

namespace Algolia\SearchAdapter\Test\Integration\Config;

use Algolia\AlgoliaSearch\Block\Configuration;
use Algolia\AlgoliaSearch\Test\Integration\TestCase;
use Algolia\SearchAdapter\Helper\ConfigHelper;
use Algolia\SearchAdapter\Model\Config\Source\QueryStringParamMode;
use Magento\Framework\View\LayoutInterface;
use Magento\TestFramework\Helper\Bootstrap;

/**
 * Tests for QueryStringParamMode routing configuration via Configuration block
 *
 * These tests verify that the UpdateConfiguration observer correctly modifies
 * the routing configuration based on the query_string_param_mode setting.
 *
 * @magentoDbIsolation disabled
 * @magentoAppIsolation enabled
 */
class RoutingConfigTest extends TestCase
{
    protected ?LayoutInterface $layout = null;
    protected ?Configuration $configurationBlock = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->layout = $this->objectManager->get(LayoutInterface::class);

        // Create the configuration block
        $this->configurationBlock = $this->layout->createBlock(Configuration::class);
    }

    /**
     * Test Magento compatible routing parameters
     *
     * @magentoConfigFixture current_store algoliasearch_instant/backend/query_string_param_mode magento
     * @magentoConfigFixture current_store algoliasearch_instant/instant/is_instant_enabled 1
     * @magentoAppArea frontend
     */
    public function testMagentoCompatibleRoutingParams(): void
    {
        $configuration = $this->configurationBlock->getConfiguration();

        $this->assertArrayHasKey('routing', $configuration);
        $routing = $configuration['routing'];

        // Verify Magento-compatible parameter names
        $this->assertEquals(
            QueryStringParamMode::SORT_PARAM_MAGENTO,
            $routing['sortingParameter'],
            'Sorting parameter should be Magento-compatible'
        );

        $this->assertEquals(
            QueryStringParamMode::PAGE_PARAM_MAGENTO,
            $routing['pagingParameter'],
            'Paging parameter should be Magento-compatible'
        );

        $this->assertEquals(
            QueryStringParamMode::CATEGORY_PARAM_MAGENTO,
            $routing['categoryParameter'],
            'Category parameter should be Magento-compatible'
        );

        // Price parameter should start with Magento-compatible prefix
        $this->assertStringStartsWith(
            QueryStringParamMode::PRICE_PARAM_MAGENTO,
            $routing['priceParameter'],
            'Price parameter should be Magento-compatible'
        );

        // Price separator should be Magento-compatible
        $this->assertEquals(
            QueryStringParamMode::PRICE_SEPARATOR_MAGENTO,
            $routing['priceRouteSeparator'],
            'Price route separator should be Magento-compatible'
        );

        // Verify isMagentoCompatible flag is true
        $this->assertTrue(
            $routing['isMagentoCompatible'],
            'isMagentoCompatible flag should be true in Magento mode'
        );
    }

    /**
     * Test Algolia default routing parameters
     *
     * @magentoConfigFixture current_store algoliasearch_instant/backend/query_string_param_mode default
     * @magentoConfigFixture current_store algoliasearch_instant/instant/is_instant_enabled 1
     * @magentoAppArea frontend
     */
    public function testAlgoliaDefaultRoutingParams(): void
    {
        $configuration = $this->configurationBlock->getConfiguration();

        $this->assertArrayHasKey('routing', $configuration);
        $routing = $configuration['routing'];

        // Verify Algolia default parameter names
        $this->assertEquals(
            QueryStringParamMode::SORT_PARAM_ALGOLIA,
            $routing['sortingParameter'],
            'Sorting parameter should be Algolia default'
        );

        $this->assertEquals(
            QueryStringParamMode::PAGE_PARAM_ALGOLIA,
            $routing['pagingParameter'],
            'Paging parameter should be Algolia default'
        );

        $this->assertEquals(
            QueryStringParamMode::CATEGORY_PARAM_ALGOLIA,
            $routing['categoryParameter'],
            'Category parameter should be Algolia default'
        );

        // Verify isMagentoCompatible flag is false
        $this->assertFalse(
            $routing['isMagentoCompatible'],
            'isMagentoCompatible flag should be false in Algolia default mode'
        );
    }

    /**
     * Test that routing config includes all required keys
     *
     * @magentoConfigFixture current_store algoliasearch_instant/instant/is_instant_enabled 1
     */
    public function testRoutingConfigHasRequiredKeys(): void
    {
        $configuration = $this->configurationBlock->getConfiguration();

        $this->assertArrayHasKey('routing', $configuration);
        $routing = $configuration['routing'];

        $requiredKeys = [
            'sortingParameter',
            'pagingParameter',
            'categoryParameter',
            'priceParameter',
            'priceRouteSeparator',
            'categoryRouteDelimiter',
        ];

        foreach ($requiredKeys as $key) {
            $this->assertArrayHasKey(
                $key,
                $routing,
                "Routing configuration should contain '$key'"
            );
        }
    }

    /**
     * Test parameter values are valid strings
     *
     * @magentoConfigFixture current_store algoliasearch_instant/instant/is_instant_enabled 1
     */
    public function testRoutingParametersAreStrings(): void
    {
        $configuration = $this->configurationBlock->getConfiguration();
        $routing = $configuration['routing'];

        $stringParams = [
            'sortingParameter',
            'pagingParameter',
            'categoryParameter',
            'priceParameter',
            'priceRouteSeparator',
        ];

        foreach ($stringParams as $param) {
            $this->assertIsString(
                $routing[$param],
                "Routing parameter '$param' should be a string"
            );
            $this->assertNotEmpty(
                $routing[$param],
                "Routing parameter '$param' should not be empty"
            );
        }
    }

    /**
     * Test price parameter format in Magento mode
     *
     * @magentoConfigFixture current_store algoliasearch_instant/backend/query_string_param_mode magento
     * @magentoConfigFixture current_store algoliasearch_instant/instant/is_instant_enabled 1
     * @magentoAppArea frontend
     */
    public function testMagentoModePriceParameterFormat(): void
    {
        $configuration = $this->configurationBlock->getConfiguration();

        // Price parameter should be 'price' + priceKey
        $this->assertStringStartsWith(
            'price',
            $configuration['routing']['priceParameter'],
            'Price parameter should start with "price"'
        );

        // Price separator should be '-' in Magento mode
        $this->assertEquals(
            '-',
            $configuration['routing']['priceRouteSeparator'],
            'Price separator should be "-" in Magento mode'
        );
    }

    /**
     * Test that observer properly updates routing config
     * This tests the UpdateConfiguration observer via the event dispatch
     *
     * @magentoConfigFixture current_store algoliasearch_instant/backend/query_string_param_mode magento
     * @magentoConfigFixture current_store algoliasearch_instant/instant/is_instant_enabled 1
     * @magentoAppArea frontend
     */
    public function testObserverUpdatesConfiguration(): void
    {
        // The Configuration block dispatches 'algolia_after_create_configuration' event
        // which is observed by UpdateConfiguration observer

        $configuration = $this->configurationBlock->getConfiguration();

        // Verify the observer has modified the routing config
        $this->assertArrayHasKey('isMagentoCompatible', $configuration['routing']);

        // In Magento mode, isMagentoCompatible should be true
        $this->assertTrue($configuration['routing']['isMagentoCompatible']);
    }
}
