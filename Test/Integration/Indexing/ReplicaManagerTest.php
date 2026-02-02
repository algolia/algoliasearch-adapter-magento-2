<?php

namespace Algolia\AlgoliaSearch\Test\Integration\Indexing;

use Algolia\AlgoliaSearch\Service\Product\SortingTransformer;
use Algolia\AlgoliaSearch\Test\Integration\TestCase;
use Algolia\SearchAdapter\Test\Integration\BackendSearchTestCase;
use Algolia\AlgoliaSearch\Service\Product\ReplicaManager;
use Algolia\SearchAdapter\Helper\ConfigHelper as AdapterConfigHelper;
use Magento\Framework\App\Config\ScopeConfigInterface;

/**
 * Tests for replica manager functionality including replica sync and indexing
 *
 * @magentoDbIsolation disabled
 * @magentoAppIsolation enabled
 */
class ReplicaManagerTest extends BackendSearchTestCase
{
    protected ?ReplicaManager $replicaManager = null;
    protected ?AdapterConfigHelper $adapterConfigHelper = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->replicaManager = $this->objectManager->get(ReplicaManager::class);
        $this->adapterConfigHelper = $this->objectManager->get(AdapterConfigHelper::class);
    }

    /**
     * Test that replica sync is enabled when backend search is enabled without InstantSearch
     *
     * @magentoConfigFixture default/catalog/search/engine algolia
     * @magentoConfigFixture current_store algoliasearch_instant/instant/is_instant_enabled 0
     * @magentoConfigFixture current_store algoliasearch_indexing_manager/algolia_indexing/enable_indexing 1
     */
    public function testReplicaSyncEnabledWithBackendSearchOnly(): void
    {
        $this->assertTrue(
            $this->adapterConfigHelper->isAlgoliaEngineSelected(),
            'Algolia should be selected as the search engine'
        );

        $isReplicaSyncEnabled = $this->replicaManager->isReplicaSyncEnabled(1);

        $this->assertTrue(
            $isReplicaSyncEnabled,
            'Replica sync should be enabled when Algolia backend search is selected, even without InstantSearch'
        );
    }

    /**
     * Test that replica sync is enabled with InstantSearch only (original behavior)
     *
     * @magentoConfigFixture default/catalog/search/engine opensearch
     * @magentoConfigFixture current_store algoliasearch_instant/instant/is_instant_enabled 1
     * @magentoConfigFixture current_store algoliasearch_indexing_manager/algolia_indexing/enable_indexing 1
     */
    public function testReplicaSyncEnabledWithInstantSearchOnly(): void
    {
        $this->assertFalse(
            $this->adapterConfigHelper->isAlgoliaEngineSelected(),
            'isAlgoliaEngineSelected should return false when a different engine is selected'
        );

        $isReplicaSyncEnabled = $this->replicaManager->isReplicaSyncEnabled(1);

        $this->assertTrue(
            $isReplicaSyncEnabled,
            'Replica sync should be enabled when InstantSearch is enabled (original core behavior)'
        );
    }

    /**
     * Test that indexing products with backend search creates replicas
     *
     * @magentoConfigFixture default/catalog/search/engine algolia
     * @magentoConfigFixture current_store algoliasearch_instant/instant/is_instant_enabled 0
     * @magentoConfigFixture current_store algoliasearch_indexing_manager/algolia_indexing/enable_indexing 1
     */
    public function testIndexingCreatesReplicasWithBackendSearchEnabled(): void
    {
        $storeId = 1;

        // Index products - this should trigger replica creation
        $this->indexAllProducts($storeId);

        // Get the primary index settings to verify replicas are configured
        $indexOptions = $this->getIndexOptions('products');

        $settings = $this->algoliaConnector->getSettings($indexOptions);

        $sorting = $this->objectManager->get(SortingTransformer::class)->getSortingIndices($storeId, null, null, true);

        $this->assertArrayHasKey(
            'replicas',
            $settings,
            'Primary index should have replicas setting after indexing with backend search enabled'
        );

        $this->assertCount(
            count($sorting),
            $settings['replicas'],
            'Primary index should have the same number of replicas as sorts'
        );
    }


    /**
     * Test that replica sync requires indexing to be enabled
     * Even with Algolia backend search, replica sync should be disabled if indexing is not enabled
     *
     * @magentoConfigFixture default/catalog/search/engine algolia
     * @magentoConfigFixture current_store algoliasearch_instant/instant/is_instant_enabled 0
     * @magentoConfigFixture current_store algoliasearch_indexing_manager/algolia_indexing/enable_indexing 0
     */
    public function testReplicaSyncRequiresIndexingEnabled(): void
    {
        $isReplicaSyncEnabled = $this->replicaManager->isReplicaSyncEnabled(1);

        $this->assertFalse(
            $isReplicaSyncEnabled,
            'Replica sync should be disabled when indexing is not enabled'
        );
    }

    /**
     * Test that both conditions must be met for backend search replica sync
     *
     * @dataProvider backendSearchReplicaSyncConditionsProvider
     * @magentoConfigFixture current_store algoliasearch_instant/instant/is_instant_enabled 0
     */
    public function testBackendSearchReplicaSyncConditions(
        string $searchEngine,
        bool   $indexingEnabled,
        bool   $expectedResult,
        string $message
    ): void
    {
        // Set the search engine
        $this->setConfig('catalog/search/engine', $searchEngine, ScopeConfigInterface::SCOPE_TYPE_DEFAULT, null);
        $this->setConfig('algoliasearch_indexing_manager/algolia_indexing/enable_indexing', $indexingEnabled ? '1' : '0');

        $isReplicaSyncEnabled = $this->replicaManager->isReplicaSyncEnabled(1);

        $this->assertEquals($expectedResult, $isReplicaSyncEnabled, $message);
    }

    /**
     * Data provider for backend search replica sync conditions
     */
    public static function backendSearchReplicaSyncConditionsProvider(): array
    {
        return [
            'Algolia engine + indexing enabled'     => [
                'algolia',
                true,
                true,
                'Should enable replica sync with Algolia engine and indexing enabled'
            ],
            'Algolia engine + indexing disabled'    => [
                'algolia',
                false,
                false,
                'Should disable replica sync when indexing is disabled'
            ],
            'OpenSearch engine + indexing enabled'  => [
                'opensearch',
                true,
                false,
                'Should disable replica sync with non-Algolia engine and no InstantSearch'
            ],
            'OpenSearch engine + indexing disabled' => [
                'opensearch',
                false,
                false,
                'Should disable replica sync with non-Algolia engine and indexing disabled'
            ],
        ];
    }

    /**
     * Test that replica sync is enabled when both InstantSearch and backend search are enabled
     *
     * @magentoConfigFixture default/catalog/search/engine algolia
     * @magentoConfigFixture current_store algoliasearch_instant/instant/is_instant_enabled 1
     * @magentoConfigFixture current_store algoliasearch_indexing_manager/algolia_indexing/enable_indexing 1
     */
    public function testReplicaSyncEnabledWithBothInstantSearchAndBackendSearch(): void
    {
        $isReplicaSyncEnabled = $this->replicaManager->isReplicaSyncEnabled(1);

        $this->assertTrue(
            $isReplicaSyncEnabled,
            'Replica sync should be enabled when both InstantSearch and Algolia backend are enabled'
        );
    }
}
