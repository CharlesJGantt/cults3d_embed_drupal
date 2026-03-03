# Cults3D Embed for Drupal

A Drupal 10+ module that integrates with the [Cults3D](https://cults3d.com) 3D printing marketplace. Provides a dedicated field type for storing Cults3D model data and a CKEditor 5 plugin for embedding model cards inline in any rich text field.

## Features

### Cults3D Model Field Type

A custom field (`cults3d_model`) that stores a Cults3D model URL along with metadata fetched from the Cults3D GraphQL API at save time:

- Model name
- Description summary (first 300 characters, truncated at word boundary)
- Download count
- Price (formatted with currency, or "Free")
- Thumbnail image URL
- Fetch timestamp

The field renders as a styled card with thumbnail, title, description, price, download count, and a "Get This Model" link back to Cults3D.

If the API is unreachable when saving, existing stored data is preserved.

### CKEditor 5 Plugin

A toolbar button (Cults3D logo) that lets editors embed model cards anywhere inside a rich text field:

1. Click the Cults3D button in the CKEditor 5 toolbar
2. Paste a Cults3D model URL in the prompt
3. The module fetches model data server-side via a secure proxy endpoint
4. A non-editable card widget is inserted at the cursor position

The saved HTML stores model metadata in `data-*` attributes on a wrapper `<div>`. A text filter (`cults3d_embed_card`) transforms these into rendered cards on page view using the same Twig template as the field formatter.

### Security

- All API communication happens server-side. Credentials never reach the browser.
- The CKEditor plugin calls a Drupal proxy endpoint (`/cults3d-embed/fetch`) that requires authentication and the `use cults3d embed ckeditor plugin` permission.
- API credentials are stored in Drupal configuration, not in code.
- All user-supplied data attributes are sanitized with `Xss::filter()` before rendering.

## Requirements

- Drupal 10.0+ (tested on 10.4)
- PHP 8.1+
- CKEditor 5 (Drupal core)
- A Cults3D account with API access

## Cults3D API Setup

This module requires a Cults3D API key. The API uses GraphQL over HTTP Basic Auth.

### Getting Your API Key

1. Log in to your [Cults3D account](https://cults3d.com)
2. Go to your [API Keys page](https://cults3d.com/en/api/keys)
3. Click **Generate a new API key**
4. Copy the generated key — you will need it during module configuration

It is recommended to create one key per integration. You can also create read-only keys if you only need to fetch data.

### API Resources

- [Cults3D API Overview](https://cults3d.com/en/api) — General API information
- [GraphQL Documentation](https://cults3d.com/en/pages/graphql) — Interactive GraphiQL explorer where you can browse the schema and test queries
- [Query Examples (Gist)](https://gist.github.com/sunny/07db54478ac030bd277c19cfe734648b) — Official query examples maintained by the Cults3D team
- [Community API Reference](https://github.com/CheekyCodexConjurer/cults3d-api-docs) — Community-maintained documentation

The API gives access to model metadata (names, descriptions, photos, tags, prices, download counts) but does not provide access to the 3D model files themselves — those remain hosted on Cults3D.

For questions about API usage, Cults3D has a dedicated Discord channel linked from their API page.

## Installation

### Via Composer (recommended)

```bash
composer require charlesgantt/cults3d_embed_drupal
drush en cults3d_embed -y
drush cr
```

### Manual

1. Download or clone this repository into your modules directory (e.g., `web/modules/contrib/cults3d_embed/`)
2. Enable the module:

```bash
drush en cults3d_embed -y
drush cr
```

## Configuration

### 1. Set API Credentials

Navigate to **Administration > Configuration > Web services > Cults3D Settings** (`/admin/config/services/cults3d`).

Enter your Cults3D username and the API key you generated above. Use the **Test Connection** button to verify your credentials are working.

### 2. Enable the CKEditor 5 Plugin

1. Go to **Administration > Configuration > Content authoring > Text formats and editors** (`/admin/config/content/formats`)
2. Edit your text format (e.g., "Full HTML")
3. Drag the **Cults3D Model Card** button into the active toolbar
4. Under **Enabled filters**, check **Cults3D Model Card**
5. Save

### 3. Add the Field to a Content Type

1. Go to **Administration > Structure > Content types** and select your content type
2. Click **Manage fields** > **Add field**
3. Select **Cults3D Model** as the field type
4. Configure cardinality (single or unlimited) as needed
5. On **Manage display**, select the **Cults3D Model Card** formatter

## Permissions

| Permission | Description |
|---|---|
| `administer cults3d embed settings` | Access the API credentials configuration form |
| `use cults3d embed ckeditor plugin` | Use the CKEditor toolbar button and API proxy endpoint |

## Theming

The module uses a single Twig template for both the field formatter and the CKEditor text filter:

**Template:** `templates/cults3d-embed-card.html.twig`

Available variables:

| Variable | Type | Description |
|---|---|---|
| `model_name` | string | The model's name on Cults3D |
| `description_summary` | string | Plain text description (max 300 chars) |
| `download_count` | int | Number of downloads on Cults3D |
| `price` | string | Formatted price (e.g., "US$ 11.71") or "Free" |
| `thumbnail_url` | string | URL to the model's illustration image |
| `cults3d_url` | string | Full URL to the model page on Cults3D |

To override the template in your theme, copy it to your theme's `templates/` directory and clear cache.

The card CSS is in `css/cults3d-embed-card.css` and is attached via the `cults3d_embed/card` library. It uses a horizontal flexbox layout that stacks vertically on mobile (<600px).

## CKEditor 5 Plugin Development

The CKEditor 5 plugin source lives in `js/ckeditor5_plugins/cults3dModel/src/`. The compiled output is committed at `js/build/cults3dModel.js`.

To rebuild after editing the plugin source:

```bash
cd /path/to/cults3d_embed
npm install
npm run build
```

Source files:

| File | Purpose |
|---|---|
| `src/index.js` | Plugin entry point and export |
| `src/cults3dmodel.js` | Toolbar button registration and UI |
| `src/cults3dmodelediting.js` | Schema, converters (upcast/downcast), command registration |
| `src/insertcults3dcardcommand.js` | Command: prompts for URL, calls the proxy, inserts the widget |
| `src/c3d.svg` | Cults3D logo icon for the toolbar button |

Build requirements: Node.js 18+, npm.

## GraphQL Query

The module fetches model data with this query:

```graphql
query {
  creation(slug: "model-slug") {
    name(locale: EN)
    description
    url
    downloadsCount
    price(currency: USD) {
      formatted
      cents
    }
    illustrationImageUrl
  }
}
```

API calls are made in two places:

- **Field widget** (`Cults3dModelWidget::massageFormValues`) — when a node with the field is saved
- **API proxy** (`Cults3dApiProxyController::fetch`) — when using the CKEditor toolbar button

## Module Structure

```
cults3d_embed/
├── config/
│   ├── install/
│   │   └── cults3d_embed.settings.yml        # Default config (empty credentials)
│   └── schema/
│       └── cults3d_embed.schema.yml          # Config schema
├── css/
│   └── cults3d-embed-card.css                # Card styles
├── js/
│   ├── build/
│   │   └── cults3dModel.js                   # Compiled CKEditor 5 plugin
│   └── ckeditor5_plugins/
│       └── cults3dModel/src/                 # CKEditor 5 plugin source
├── src/
│   ├── Controller/
│   │   └── Cults3dApiProxyController.php     # Server-side API proxy for CKEditor
│   ├── Form/
│   │   └── Cults3dSettingsForm.php           # Admin config form
│   └── Plugin/
│       ├── Field/
│       │   ├── FieldFormatter/
│       │   │   └── Cults3dModelCardFormatter.php
│       │   ├── FieldType/
│       │   │   └── Cults3dModelItem.php
│       │   └── FieldWidget/
│       │       └── Cults3dModelWidget.php
│       └── Filter/
│           └── Cults3dCardFilter.php         # Text filter for CKEditor embeds
├── templates/
│   └── cults3d-embed-card.html.twig          # Card template (shared by field + filter)
├── cults3d_embed.ckeditor5.yml               # CKEditor 5 plugin registration
├── cults3d_embed.info.yml                    # Module info
├── cults3d_embed.libraries.yml               # Asset libraries
├── cults3d_embed.links.menu.yml              # Admin menu link
├── cults3d_embed.module                      # hook_theme()
├── cults3d_embed.permissions.yml             # Permission definitions
├── cults3d_embed.routing.yml                 # Route definitions
├── package.json                              # Node.js dependencies for CKEditor build
└── webpack.config.js                         # Webpack config for CKEditor build
```

## License

GPL-2.0-or-later
