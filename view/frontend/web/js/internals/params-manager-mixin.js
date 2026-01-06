define(function () {
    return function (target) {
        const mixin = {
            getProductIndexName() {
                return algoliaConfig.indexName + '_products';
            },

            isMagentoCompatibleMode() {
                return algoliaConfig.routing?.isMagentoCompatible ?? false;
            },

            getSortingValueFromUiState(uiStateProductIndex) {
                if (!this.isMagentoCompatibleMode()) {
                    return target.getSortingValueFromUiState(uiStateProductIndex);
                }

                return this.replicaToSortParam(this.getProductIndexName(), uiStateProductIndex.sortBy);
            },

            getSortingFromRoute(routeParameters) {
                if (!this.isMagentoCompatibleMode()) {
                    return target.getSortingFromRoute(routeParameters);
                }

                return this.sortParamToReplica(this.getProductIndexName(), routeParameters[this.getSortingParam()]);
            },

            replicaToSortParam(productIndexName, replica) {
                // if no replica is selected, we don't want it to be part of the url
                if (replica === undefined) {
                    return;
                }

                // Remove the main product index name so we keep only the replica suffix
                const rawSorting = replica.replace(productIndexName + '_', '');
                // Get only the direction
                const direction = rawSorting.split('_').slice(-1);
                // Isolate the sort by removing the direction
                let sort = rawSorting.replace('_' + direction, '');

                // Edge case for prices replicas => remove the price group
                if (sort.includes('price')) {
                    sort = sort.replace('_' + algoliaConfig.priceGroup, '');
                }

                return [sort, direction].join("~");
            },

            sortParamToReplica(productIndexName, sortParam) {
                if (sortParam === undefined) {
                    return;
                }

                // Get both sort and direction from param
                let explodedSortParam = sortParam.split("~"), sort = explodedSortParam[0], direction = explodedSortParam[1];

                // Edge case for prices => re-add the price group to retrieve the right replica
                if (sort === 'price') {
                    sort = 'price_' + algoliaConfig.priceGroup;
                }

                // Return recomputed replica index name
                return productIndexName + '_' + sort + '_' + direction;
            },
        };

        return { ...target, ...mixin };
    };
});
