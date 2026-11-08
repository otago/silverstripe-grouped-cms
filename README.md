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

### Group options

Every option is per group and optional:

```yaml
SilverStripe\Admin\LeftAndMain:
  menu_groups:
    Money:
      icon_class: font-icon-credit-card   # default: font-icon-p-archive
      priority: 95                         # 0 counts as a priority
      keep_label: true                     # a one-item group keeps its own title and icon
      heading: 'Business'                  # a small heading above the group
      divider: true                        # a line above the group
      items:
        - App-Admins-InvoiceAdmin
```

A group with a single item normally renders as that item. With `keep_label: true` it keeps the group's title and icon, linking to its item.

### Badges

A section can show a count beside its menu entry. Give its `LeftAndMain` class a public static `menu_badge()`:

```php
public static function menu_badge(): int|array|null
{
    $count = Enquiry::get()->filter('Status', 'new')->count();

    // An int or string, or ['value' => …, 'tone' => 'alert'|'info'] for a coloured pill.
    // null, 0 or '' shows nothing.
    return $count ?: null;
}
```

A group's header shows the total of its items' numeric badges while it is closed, so a count is never hidden inside a collapsed group (an `alert` tone carries to the header). An extension on `LeftAndMain` can change any badge with `updateMenuBadge(&$badge, $code)`. The menu is built on every CMS page, so keep `menu_badge()` cheap or cache it.

Before badges, the usual way was to append the count to `menu_title()`, which also put it in page titles and breadcrumbs.

### Hiding items

List menu codes under `menu_groups_hidden_items` to leave them off the menu. They stay registered and reachable by URL; this is the YAML equivalent of `CMSMenu::remove_menu_item()`:

```yaml
SilverStripe\Admin\LeftAndMain:
  menu_groups_hidden_items:
    - SilverStripe-CampaignAdmin-CampaignAdmin
    - SilverStripe-VersionedAdmin-ArchiveAdmin
```

### Active item

```yaml
SilverStripe\Admin\LeftAndMain:
  # Match the current URL on a path-segment boundary, so /admin/social-posts no longer
  # highlights /admin/social. Off by default (the CMS matches any prefix).
  menu_groups_segment_matching: true
  # While a hidden or secondary section is open, keep another entry highlighted.
  menu_groups_also_active_for:
    App-Admins-SocialDashboardAdmin:
      - App-Admins-SocialPostAdmin
```

### Open groups

Clicking a group's header opens and closes the group (in the collapsed icon rail it still opens the group's first item). By default only the group holding the current section is opened on each page. With

```yaml
SilverStripe\Admin\LeftAndMain:
  menu_groups_remember_open: true
```

groups opened by hand stay open, several at once, and are remembered in the browser's `localStorage` between page loads (if storage is unavailable they last until the next page load).

## How it works

- **`GroupedCMSMenu`** extends `LeftAndMain` and adds `GroupedMainMenu()` / `BrandedMainMenu()` methods
- The template `LeftAndMain_MenuList.ss` calls `$BrandedMainMenu` instead of `$MainMenu`
- Items in multi-item groups render as collapsible parent rows with a child list
- Single-item groups (or ungrouped items) render as normal flat rows
- The JS (`cms-menu.js`) keeps the open/active state in sync across PJAX navigations, opens and closes a group when its header is clicked, and (with `menu_groups_remember_open`) remembers groups opened by hand
- `menu_badge()` counts are summed onto closed group headers

## Upgrading from 1.0

Nothing changes unless you opt in, except these fixes:

- A group header click now opens or closes the group. On Silverstripe Admin 3 it navigated to the group's first item, so a group could only be opened by leaving the page.
- A group without `icon_class` falls back to `font-icon-p-archive` (the CMS icon font); `far fa-folder-open` needed FontAwesome, which the CMS does not load.
- `priority: 0` is honoured instead of being treated as unset.
- A group title is made safe for its HTML id and translation key: `Music & art` becomes `Music_art` (`GroupedCmsMenuLabel.Music_art`). A title of letters, digits, `_` and `-`, with spaces as before, keeps its key.
- The group header links to its first item in the configured order.
- The chevron uses the icon font's `font-icon-right-open` and turns down when open, instead of swapping glyphs by `content`.
- The expanded sidebar's fixed 18.4rem width applies from 768px up; below that the CMS's own drawer width is used.
