<?php

namespace Otago\GroupedCMS\Extensions;

use SilverStripe\Admin\AdminRootController;
use SilverStripe\Admin\LeftAndMain;
use SilverStripe\Control\Controller;
use SilverStripe\Core\Config\Config;
use SilverStripe\Core\Convert;
use SilverStripe\Core\Extension;
use SilverStripe\Model\ArrayData;
use SilverStripe\Model\List\ArrayList;
use SilverStripe\Model\List\GroupedList;
use SilverStripe\ORM\FieldType\DBField;
use SilverStripe\ORM\FieldType\DBHTMLText;
use SilverStripe\View\Requirements;

class GroupedCMSMenu extends Extension
{
    use ExtensionDefinesDefaultConfig;

    public const CONFIG_SETTINGS_WITH_DEFAULTS = [
        'menu_groups',
        'menu_groups_alphabetical_sorting',
        'menu_groups_hidden_items',
        'menu_groups_also_active_for',
        'menu_groups_segment_matching',
        'menu_groups_remember_open',
    ];

    /**
     * Fallback icon for a group without an icon_class. It is in the CMS's own icon font,
     * which is always loaded (FontAwesome is not).
     */
    public const DEFAULT_GROUP_ICON = 'font-icon-p-archive';

    private static $menu_groups = [];

    private static $menu_groups_alphabetical_sorting = false;

    /**
     * Menu codes left out of the menu. The sections stay registered and reachable by URL;
     * only their sidebar entries go. The YAML equivalent of CMSMenu::remove_menu_item().
     */
    private static $menu_groups_hidden_items = [];

    /**
     * Menu code => list of other menu codes. While one of the listed sections is open, the
     * keyed item is shown as the current one (e.g. a hidden record list reached from a
     * dashboard keeps the dashboard highlighted).
     */
    private static $menu_groups_also_active_for = [];

    /**
     * When true, an item is only "current" if the current URL is its URL or lies under it on
     * a path-segment boundary, so /admin/social-posts no longer highlights /admin/social.
     * Off by default: the stock CMS matches on any prefix.
     */
    private static $menu_groups_segment_matching = false;

    /**
     * When true, groups opened by hand stay open (several at once) and are remembered in
     * the browser between page loads. Off by default: only the active group is open.
     */
    private static $menu_groups_remember_open = false;

    public function onAfterInit(): void
    {
        Requirements::css('otago/silverstripe-grouped-cms: client/cms-theme.css');
        Requirements::javascript('otago/silverstripe-grouped-cms: client/cms-menu.js');
    }

    /**
     * Used by LeftAndMain_MenuList.ss. Falls back to MainMenu() when no groups are configured.
     */
    public function BrandedMainMenu(): ArrayList
    {
        return $this->GroupedMainMenu();
    }

    /**
     * Attributes for the menu's <ul>, read by cms-menu.js.
     */
    public function GroupedMenuAttributes(): DBHTMLText
    {
        $config = LeftAndMain::config();
        $attributes = [];
        if ($config->get('menu_groups_segment_matching')) {
            $attributes[] = 'data-segment-matching="1"';
        }
        if ($config->get('menu_groups_remember_open')) {
            $attributes[] = 'data-remember-open="1"';
        }

        return DBField::create_field('HTMLFragment', implode(' ', $attributes));
    }

    /**
     * Returns the full menu as a grouped ArrayList.
     * Items not assigned to a group appear as individual flat entries.
     */
    public function GroupedMainMenu(): ArrayList
    {
        $config = LeftAndMain::config();
        $hidden = (array) $config->get('menu_groups_hidden_items');
        $items = $this->owner->MainMenu()->filterByCallback(
            fn ($item) => !in_array((string) $item->Code, $hidden, true)
        );
        $result = ArrayList::create();
        $menus = (array) $config->get('menu_groups');
        $itemsToGroup = [];
        $menuSort = 0;
        $itemSort = 0;

        foreach ($menus as $title => $settings) {
            $settings = (array) $settings;
            $children = array_key_exists('items', $settings) ? (array) $settings['items'] : [];
            $priority = isset($settings['priority']) && is_numeric($settings['priority'])
                ? $settings['priority']
                : null;

            if (count($items)) {
                foreach ($children as $key => $item) {
                    if (is_numeric($key)) {
                        $itemsToGroup[$item] = [
                            'Group'     => $title,
                            'Priority'  => $priority !== null ? $priority : $menuSort,
                            'SortOrder' => $itemSort,
                        ];
                    }
                    $itemSort++;
                }
                $menuSort--;
            }
        }

        foreach ($items as $item) {
            $code = Convert::raw2xml($item->Code);

            $this->applyLinkingMode($item, (string) $item->Code);
            $this->applyBadge($item);

            if (array_key_exists($code, $itemsToGroup)) {
                $item->Group    = $itemsToGroup[$code]['Group'];
                $item->Priority = $itemsToGroup[$code]['Priority'];
                $item->SortOrder = $itemsToGroup[$code]['SortOrder'];
            } else {
                $item->Group    = $code;
                $item->Priority = is_numeric($item->MenuItem->priority) ? $item->MenuItem->priority : -1;
                $item->SortOrder = 0;
            }
        }

        foreach (GroupedList::create($items->sort(['Priority' => 'DESC']))->groupBy('Group') as $group => $children) {
            $settings = (array_key_exists($group, $menus) && is_array($menus[$group])) ? $menus[$group] : [];
            $icon = !empty($settings['icon_class']) ? $settings['icon_class'] : self::DEFAULT_GROUP_ICON;
            $code = static::groupCode($group);
            $label = _t('GroupedCmsMenuLabel.' . $code, $group);

            if (count($children) > 1) {
                $active = false;
                $badge = 0;
                $badgeTone = '';
                foreach ($children as $child) {
                    if ($child->LinkingMode == 'current') {
                        $active = true;
                    }
                    if (is_numeric($child->BadgeValue)) {
                        $badge += (int) $child->BadgeValue;
                    }
                    if ($child->BadgeTone && ($badgeTone === '' || $child->BadgeTone === 'alert')) {
                        $badgeTone = $child->BadgeTone;
                    }
                }

                $sorted = $config->get('menu_groups_alphabetical_sorting')
                    ? $children->sort('Title')
                    : $children->sort('SortOrder');

                $row = ArrayData::create([
                    'Title'       => $label,
                    'Code'        => DBField::create_field('Text', $code),
                    // The header links to the group's first entry as listed, not as it came
                    // out of the priority sort.
                    'Link'        => $sorted->First()->Link,
                    'Icon'        => $icon,
                    'LinkingMode' => $active ? 'current' : '',
                    'Badge'       => $badge > 0 ? (string) $badge : '',
                    'BadgeTone'   => $badge > 0 ? $badgeTone : '',
                    'Children'    => $sorted,
                ]);
            } else {
                $row = $children->First();
                if (!empty($settings['keep_label'])) {
                    // A one-item group keeps its own title and icon instead of becoming its child.
                    $row->Title = $label;
                    $row->IconClass = $icon;
                    $row->HasCSSIcon = false;
                }
            }

            if (!empty($settings['heading'])) {
                $row->MenuHeading = _t('GroupedCmsMenuHeading.' . $code, (string) $settings['heading']);
            }
            if (!empty($settings['divider']) || !empty($settings['heading'])) {
                $row->MenuDivider = true;
            }

            $result->push($row);
        }

        return $result;
    }

    /**
     * A group title made safe for an HTML id and a translation key: "Music & art" → "Music_art".
     */
    public static function groupCode(string $group): string
    {
        $code = trim(preg_replace('/[^A-Za-z0-9_-]+/', '_', $group), '_');

        return $code !== '' ? $code : 'Group';
    }

    /**
     * Applies the optional segment-boundary matching and the also_active_for map.
     */
    protected function applyLinkingMode($item, string $code): void
    {
        $config = LeftAndMain::config();
        $owner = $this->owner;
        $menuItem = $item->MenuItem;

        if ($config->get('menu_groups_segment_matching')
            && $item->LinkingMode === 'current'
            && $menuItem
            && $menuItem->controller !== get_class($owner)
            && !static::pathWithin((string) $owner->Link(), (string) $menuItem->url)
        ) {
            $item->LinkingMode = 'link';
        }

        $alsoActive = (array) $config->get('menu_groups_also_active_for');
        $others = isset($alsoActive[$code]) ? (array) $alsoActive[$code] : [];
        if (!$others) {
            return;
        }

        $paths = [];
        foreach ($others as $other) {
            $class = str_replace('-', '\\', (string) $other);
            if (get_class($owner) === $class) {
                $item->LinkingMode = 'current';
            }
            if (class_exists($class)) {
                $segment = Config::inst()->get($class, 'url_segment');
                if ($segment) {
                    $paths[] = Controller::join_links(AdminRootController::admin_url(), $segment);
                }
            }
        }
        $item->AlsoActivePaths = implode(' ', $paths);
    }

    /**
     * True when $path is $base or lies under it on a "/" boundary.
     */
    public static function pathWithin(string $path, string $base): bool
    {
        $path = strtok($path, '?#') ?: '';
        $base = strtok($base, '?#') ?: '';

        return str_starts_with(rtrim($path, '/') . '/', rtrim($base, '/') . '/');
    }

    /**
     * A section can show a count beside its menu entry: give its LeftAndMain class a public
     * static menu_badge() returning an int, a string, or ['value' => …, 'tone' => 'alert'],
     * or null/0 for none. Extensions can change it with updateMenuBadge(&$badge, $code).
     */
    protected function applyBadge($item): void
    {
        $badge = null;
        $class = $item->MenuItem ? $item->MenuItem->controller : null;
        if ($class && method_exists($class, 'menu_badge')) {
            try {
                $badge = $class::menu_badge();
            } catch (\Throwable $e) {
                $badge = null;
            }
        }
        $this->owner->extend('updateMenuBadge', $badge, $item->Code);

        $tone = '';
        if (is_array($badge)) {
            $tone = isset($badge['tone']) ? (string) $badge['tone'] : '';
            $badge = $badge['value'] ?? null;
        }
        if ($badge === null || $badge === '' || $badge === 0 || $badge === '0' || $badge === false) {
            $item->Badge = '';
            $item->BadgeValue = null;
            $item->BadgeTone = '';
            return;
        }

        $item->Badge = (string) $badge;
        $item->BadgeValue = $badge;
        $item->BadgeTone = $tone;
    }
}
