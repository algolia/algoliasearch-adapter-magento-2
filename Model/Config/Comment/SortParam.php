<?php

namespace Algolia\SearchAdapter\Model\Config\Comment;

use Algolia\AlgoliaSearch\Model\Config\AbstractConfigComment;
use Algolia\AlgoliaSearch\Service\IndexNameFetcher;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\UrlInterface;

class SortParam extends AbstractConfigComment
{
    public function __construct(
        protected RequestInterface $request,
        protected UrlInterface     $urlInterface,
        protected IndexNameFetcher $indexNameFetcher
    ){
        parent::__construct($request, $urlInterface);
    }

    public function getCommentText($elementValue): string
    {
        $productIndex = $this->indexNameFetcher->getIndexName('_products') . '_price_default_asc';

        $link = $this->getConfigLink(
            'catalog',
            'row_catalog_search_algolia_seo_filters'
        );

        return <<<COMMENT
            <div class="algolia-help-content">
            <p>Specify the query string parameters you want to use in your product listing URLs.</p>

            <p>There are two options: <strong>Algolia default</strong> (classic InstantSearch) and <strong>Magento compatibility mode</strong> (default Magento urls).</p>

            <p>When using backend render together with InstantSearch, it is recommended to use Magento compatibility mode so URLs indexed by search engines will render correctly in InstantSearch.</p>

            <ul>
                <li>
                    <strong>Algolia default</strong>
                    <br/>
                    Example: <code>http//mywebsite.com/?<strong>sortBy</strong>=$productIndex&<strong>categories</strong>=Gear&<strong>price.USD.default</strong>=40:60&<strong>page</strong>=2</code>
                </li>
                <li>
                    <strong>Magento compatibility mode</strong>
                    <br/>
                    Example: <code>http//mywebsite.com/?<strong>product_list_order</strong>=price~asc&<strong>cat</strong>=Gear&<strong>price</strong>=40-60&<strong>p</strong>=2</code>
                </li>
            </ul>

            <table class="algolia-help-table">
                <thead>
                    <tr>
                        <th></th>
                        <th>Algolia default</th>
                        <th>Magento compatibility mode</th>
                    </tr>
                </thead>
                <tbody>
                <tr>
                    <td>Sorting</td>
                    <td><code>sortBy</code>
                        <br/><br/>
                        <small>The "sortBy" parameter will be associated to an Algolia replica index.</small>
                    </td>
                    <td><code>product_list_order</code>
                        <br/><br/>
                        <small>The "product_list_order" parameter will be associated to "sort~direction" pair and will replicate the default Magento urls.</small>
                    </td>
                </tr>
                <tr>
                    <td>Pagination</td>
                    <td><code>page</code></td>
                    <td><code>p</code></td>
                </tr>
                <tr>
                    <td>Price filtering</td>
                    <td><code>price.%CURRENCY%.%CUSTOMER_GROUP%</code>
                    <br/><br/>
                    <small>Example: <code>price.USD.default</code></small>
                    </td>
                    <td><code>price</code></td>
                </tr>
                 <tr>
                    <td>Category filtering</td>
                    <td><code>categories</code></td>
                    <td><code>cat</code></td>
                </tr>
                </tbody>
            </table>

            <aside>
                <p><strong>Note:</strong> For best results with InstantSearch, we also recommend enabling <a href="$link">SEO filters</a>.</p>
            </aside>

            </div>
            COMMENT;
    }
}
