# Algolia Search Adapter for Magento 2

Registers Algolia as a native Magento 2 search engine and provides backend (server-side) rendering for category pages and search results. 
When enabled, Magento's standard product listing blocks query Algolia directly - no InstantSearch JavaScript required - making content visible 
to search-engine crawlers, LLM-based discovery tools, and any client that doesn't execute JavaScript.

The adapter can operate **independently** (pure server-side rendering) or **alongside InstantSearch** (server-rendered pages for bots, client-rendered for humans).

## Requirements

- PHP 8.2+
- Magento 2.4+ (framework `^103.0`)
- `algolia/algoliasearch-magento-2` ^3.18

## Installation

### Via Composer (Recommended)

```bash
composer require algolia/algoliasearch-adapter-magento-2
bin/magento module:enable Algolia_SearchAdapter
bin/magento setup:upgrade
bin/magento setup:di:compile
bin/magento cache:flush
```

### Manual Installation

1. Download the module files.
2. Place them in `app/code/Algolia/SearchAdapter/`.
3. Run the commands above starting from `module:enable`.

## Enabling the Adapter

> If you haven't already done so, first navigate to 
> **Stores > Configuration > Algolia Search > Credentials and Basic Setup**
> and make sure you supply your **Algolia Application ID** and corresponding API keys. 
> The backend search adapter uses the same credentials. 

1. Navigate to **Stores > Configuration > Catalog > Catalog Search**.
2. Set **Search Engine** to **Algolia Backend Search**.
3. Adjust connection and read timeouts if needed (defaults: 2s / 5s).
4. Use the **Test Connection** button to verify connectivity.
5. Save and flush cache.

> **Note:** The adapter does NOT require InstantSearch to be enabled. It can power category and search result pages 
> entirely on its own via server-side rendering in tandem with Magento's native layered navigation.

## Backend Rendering

**Path:** Stores > Configuration > Algolia Search > InstantSearch > Backend Search Compatibility

Backend rendering controls whether Magento renders product listings on the server before delivering the page. Three modes are available:

| Mode | Config Value | Behavior |
|------|-------------|----------|
| **No** (default) | `0` | InstantSearch handles all rendering client-side. |
| **Yes (for all users)** | `1` | Server renders results for every request. |
| **Yes, for specific User Agents** | `2` | Server renders only when the request's `User-Agent` matches the allow list. |

### User-Agent Allow List

When mode is set to **specific User Agents**, configure the allow list at:

**Path:** `algoliasearch_instant/backend/backend_render_allowed_user_agents`

Enter one user-agent string per line. Matching is **partial and case-insensitive**, e.g. `Googlebot` 
will match `Mozilla/5.0 (compatible; Googlebot/2.1; ...)`.

**Defaults:** `Googlebot`, `Bingbot` (one per line).

### Cache Context (`X-Magento-Vary`)

When backend rendering is enabled for specific user agents, the module adds an `algolia_rendering_context` value 
(`with_backend` or `without_backend`) to Magento's `X-Magento-Vary` cookie. 
This tells Magento's full-page cache (FPC) to store **separate cached pages** for bot visitors vs. human visitors, 
so each group gets the correct rendering.

This context is only applied when InstantSearch is configured to replace category pages.

## SEO-Friendly Filters

**Path:** Stores > Configuration > Catalog > Catalog Search > Enable SEO Friendly Filters
**Default:** Enabled

Converts Magento's internal option-ID-based filter URLs into human-readable, label-based URLs:

```
Before:  ?color=49&size=167
After:   ?color=blue&size=large
```

This is **strongly recommended** when using backend rendering alongside InstantSearch, as it ensures 
both rendering modes produce identical, indexable URLs.

## Query String Parameter Parity

**Path:** Stores > Configuration > Algolia Search > InstantSearch > Backend Search Compatibility > Query string parameters

This setting ensures that backend-rendered pages and InstantSearch use **identical URL parameter names**. 
When a crawler indexes a URL like `?product_list_order=price~asc&p=2&cat=5&price=10-50`, 
a human visitor opening the same URL gets identical results from InstantSearch.

Two modes are available:

| Parameter | Algolia Default (`default`) | Magento Compatibility (`magento`) |
|-----------|----------------------------|----------------------------------|
| Sorting | `sortBy` | `product_list_order` |
| Pagination | `page` | `p` |
| Category | `categories` | `cat` |
| Price | `price.{priceKey}` with `:` separator | `price` with `-` separator |

**Default:** Magento compatibility mode.

**How it works:** The `UpdateConfiguration` observer injects the selected parameter mapping into the InstantSearch JavaScript configuration under the `routing` key. A JS mixin (`params-manager-mixin`) reads this configuration and transforms InstantSearch's native routing to use the selected parameter names - achieving URL parity between server-rendered and client-rendered pages.

## Varnish & User-Agent Caching

When using the **specific User Agents** rendering mode behind Varnish, be aware of an important caching interaction:

The module uses Magento's `X-Magento-Vary` cookie (via `HttpContext`) to tell the FPC to cache separate page variants 
for bots vs. humans. This works correctly with Magento's built-in FPC. 
However, **search engine crawlers do not carry cookies**, so Varnish has no `X-Magento-Vary` cookie to differentiate 
bot requests from human requests on cached hits.

**Result:** Varnish may serve the wrong cached variant - a human could see the bot (backend-rendered) page, 
or a crawler could see the JS-only page.

**Solution:** Add a custom VCL snippet that inspects the `User-Agent` header and synthesizes or overrides 
the vary hash for known bot user agents, ensuring Varnish serves the correct variant without relying on cookies. 
If you use the **Yes (for all users)** mode, this problem does not apply because every visitor gets the same server-rendered page.

## Limitations

- **Landing pages** are not supported. Backend rendering applies to **category product listing pages** and **`catalogsearch/result`** only.
- **Advanced Search** is not supported at this time. 

## Configuration Reference

| Config Path | Label | Type | Default |
|-------------|-------|------|---------|
| `catalog/search/engine` | Search Engine | select | - |
| `catalog/search/algolia_app_id` | Algolia Application ID | text | - |
| `catalog/search/algolia_connect_timeout` | Connection Timeout (seconds) | text | `2` |
| `catalog/search/algolia_read_timeout` | Read Timeout (seconds) | text | `5` |
| `catalog/search/algolia_seo_filters` | Enable SEO Friendly Filters | yes/no | `Yes` |
| `algoliasearch_instant/backend/backend_render_mode` | Enable backend rendering | select | `No` (0) |
| `algoliasearch_instant/backend/backend_render_allowed_user_agents` | Allowed User Agents | textarea | `Googlebot`, `Bingbot` |
| `algoliasearch_instant/backend/query_string_param_mode` | Query string parameters | select | `magento` |

## License

This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.
