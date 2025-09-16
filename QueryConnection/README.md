# CommerceOptimizer QueryConnection Module

This module provides CLI commands for managing Adobe Commerce Optimizer (ACO) configuration settings within Magento.

## Overview

The QueryConnection module offers two main CLI commands:
- `comopt:config:get` - Retrieve Commerce Optimizer configuration values
- `comopt:config:set` - Set Commerce Optimizer configuration values

These commands allow you to manage ACO connection settings from the command line.

## Available Configuration Paths

The module manages the following ACO connection settings:

| Configuration Path | Description | Example Value |
|-------------------|-------------|---------------|
| `comopt/settings/aco/connection/base_uri` | Base URI for Adobe Commerce Optimizer API | `https://na1-sandbox.api.commerce.adobe.com` |
| `comopt/settings/aco/connection/ac_channel_id` | Adobe Commerce Channel ID | `c0780d24-00b0-4236-bc31-ba586d3e7f0b` |
| `comopt/settings/aco/connection/ac_environment_id` | Adobe Commerce Environment ID | `KZrr4s3gAAbumMGicqrvVo` |
| `comopt/settings/aco/connection/ac_price_book_id` | Adobe Commerce Price Book ID | `west_coast_inc` |
| `comopt/settings/aco/connection/ac_scope_locale` | Adobe Commerce Scope Locale | `en-US` |

## CLI Commands

### 1. Get Configuration Values - `comopt:config:get`

Retrieves Commerce Optimizer configuration values.

#### Syntax
```bash
bin/magento comopt:config:get [options]
```

#### Options
- `--path` or `-p`: Specific configuration path to retrieve
- `--scope` or `-s`: Configuration scope (default, website, store) - defaults to 'default'
- `--scope-id` or `-i`: Scope ID (required for website/store scope) - defaults to 0

#### Examples

**Get all ACO connection settings:**
```bash
bin/magento comopt:config:get
```

Output:
```
ACO Connection Settings:

  Base URI: https://na1-sandbox.api.commerce.adobe.com
  AC Channel ID: c0780d24-00b0-4236-bc31-ba586d3e7f0b
  AC Environment ID: KZrr4s3gAAbumMGicqrvVo
  AC Price Book ID: west_coast_inc
  AC Scope Locale: en-US
```

#### Examples


**Set configuration:**

```bash
# Set base URI
bin/magento comopt:config:set \
  --key base_uri \
  --value "https://na1-sandbox.api.commerce.adobe.com"

# Set environment ID
bin/magento comopt:config:set \
  --key ac_environment_id \
  --value "KZrr4s3gAAbumMGicqrvVo"

# Set channel ID
bin/magento comopt:config:set \
  --key ac_channel_id \
  --value "c0780d24-00b0-4236-bc31-ba586d3e7f0b"

# Set price book ID
bin/magento comopt:config:set \
  --key ac_price_book_id \
  --value "west_coast_inc"

# Set locale
bin/magento comopt:config:set \
  --key ac_scope_locale \
  --value "en-US"
```
