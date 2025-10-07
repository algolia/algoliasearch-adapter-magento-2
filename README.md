# Algolia Search Adapter for Magento 2

A Magento 2 extension that provides backend rendering support for Algolia search functionality.

## Overview

The `Algolia_SearchAdapter` module extends Magento 2's search capabilities by integrating with Algolia's search and discovery platform. This module specifically focuses on backend rendering support, enabling server-side search result processing and rendering.

## Features

- Backend rendering support for Algolia search
- Integration with Magento 2's catalog search functionality
- Compatible with Magento 2.4+ and PHP 8.2+

## Installation

### Via Composer (Recommended)

```bash
composer require algolia/algoliasearch-adapter-magento-2
```

### Manual Installation

1. Download the module files
2. Place them in `app/code/Algolia/SearchAdapter/`
3. Run the following commands:

```bash
php bin/magento module:enable Algolia_SearchAdapter
php bin/magento setup:upgrade
php bin/magento setup:di:compile
php bin/magento cache:flush
```

## Requirements

- Magento 2.4+
- PHP 8.2+
- Algolia Search & Discovery extension

## Configuration

This module works in conjunction with the main Algolia Search extension. Please refer to the main Algolia Search documentation for configuration details.

## License

This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.

## Support

For support and questions, please refer to the main Algolia Search extension documentation or contact Algolia support.
