define(function () {
    return function (target) {

        const PRICE_DELTA  = 0.01;

        const mixin = {
            getProductIndexName() {
                return algoliaConfig.indexName + '_products';
            },

            isMagentoCompatibleMode() {
                return algoliaConfig.routing?.isMagentoCompatible ?? false;
            },

            getPriceParam() {
                return algoliaConfig.routing.priceParameter;
            },

            getPriceParamValue(currentFacetAttribute, routeParameters) {
                // Price param should be fetched dynamically because it can be either Magento or Algolia based
                // Fallbacks to the original parameter in case it's not found
                let priceParamValue = routeParameters[this.getPriceParam()] ?
                    routeParameters[this.getPriceParam()]?.replace(this.getPriceSeparator(), ':') :
                    routeParameters[algoliaConfig.routing.originalPriceParameter]?.replace(algoliaConfig.routing.originalPriceRouteSeparator, ':');

                if (routeParameters[this.getPriceParam()]) {
                    return this.transformPriceUpperBoundary(priceParamValue);
                }

                return priceParamValue;
            },

            transformPriceUpperBoundary(range) {
                if (!this.isMagentoCompatibleMode() || !range) {
                    return range;
                }

                const rangeValues = range.split(':');
                rangeValues[1] = (parseInt(rangeValues[1]) - PRICE_DELTA).toString();
                return rangeValues.join(':');
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
