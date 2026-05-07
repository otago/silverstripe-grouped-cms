# silverstripe-grouped-cms

Collapsible grouped navigation menu for the SilverStripe CMS sidebar.

Groups related admin sections under labelled, collapsible parent items with icons. When the sidebar is collapsed to icon-only mode, groups are shown as individual icon rows with their children visible on hover.

## Requirements

- SilverStripe Admin ^3.0
- PHP ^8.1

## Installation

```bash
composer require otago/silverstripe-grouped-cms
```

## Usage

The extension registers itself automatically. Define groups in your project's YAML config:

```yaml
SilverStripe\Admin\LeftAndMain:
  menu_groups:
    Content:
      icon_class: font-icon-menu-pages
      items:
        - SilverStripe-CMS-Controllers-CMSMain
        - SilverStripe-AssetAdmin-Controller-AssetAdmin
    Settings:
      icon_class: font-icon-cog
      items:
        - SilverStripe-Admin-SecurityAdmin
        - SilverStripe-SiteConfig-SiteConfigLeftAndMain
        - SilverStripe-Reports-ReportAdmin
```

Admin class names use hyphens as namespace separators, e.g. `App\Admins\NewsAdmin` → `App-Admins-NewsAdmin`.

Items not listed in any group appear as flat (ungrouped) entries in the menu.

### Options

```yaml
SilverStripe\Admin\LeftAndMain:
  # Sort children alphabetically instead of by declaration order
  menu_groups_alphabetical_sorting: false
```

### Group priority

Add an optional `priority` key to a group to control its position relative to ungrouped items:

```yaml
  menu_groups:
    Content:
      icon_class: font-icon-menu-pages
      priority: 10
      items:
        - SilverStripe-CMS-Controllers-CMSMain
```

## How it works

- **`GroupedCMSMenu`** extends `LeftAndMain` and adds `GroupedMainMenu()` / `BrandedMainMenu()` methods
- The template `LeftAndMain_MenuList.ss` calls `$BrandedMainMenu` instead of `$MainMenu`
- Items in multi-item groups render as collapsible parent rows with a child list
- Single-item groups (or ungrouped items) render as normal flat rows
- The JS (`cms-menu.js`) keeps the open/active state in sync across PJAX navigations
