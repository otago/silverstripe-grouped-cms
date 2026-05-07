<?php

namespace Otago\GroupedCMS\Extensions;

use SilverStripe\Admin\LeftAndMain;
use SilverStripe\Core\Convert;
use SilverStripe\Core\Extension;
use SilverStripe\Model\ArrayData;
use SilverStripe\Model\List\ArrayList;
use SilverStripe\Model\List\GroupedList;
use SilverStripe\ORM\FieldType\DBField;
use SilverStripe\View\Requirements;

class GroupedCMSMenu extends Extension
{
    use ExtensionDefinesDefaultConfig;

    public const CONFIG_SETTINGS_WITH_DEFAULTS = [
        'menu_groups',
        'menu_groups_alphabetical_sorting',
    ];

    private static $menu_groups = [];

    private static $menu_groups_alphabetical_sorting = false;

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
     * Returns the full menu as a grouped ArrayList.
     * Items not assigned to a group appear as individual flat entries.
     */
    public function GroupedMainMenu(): ArrayList
    {
        $items = $this->owner->MainMenu();
        $result = ArrayList::create();
        $config = LeftAndMain::config();
        $menus = $config->get('menu_groups');
        $itemsToGroup = [];
        $menuSort = 0;
        $itemSort = 0;

        foreach ($menus as $title => $settings) {
            $children = array_key_exists('items', $settings) ? $settings['items'] : [];
            $priority = array_key_exists('priority', $settings) ? $settings['priority'] : null;

            if (count($items)) {
                foreach ($children as $key => $item) {
                    if (is_numeric($key)) {
                        $itemsToGroup[$item] = [
                            'Group'     => $title,
                            'Priority'  => $priority ? $priority : $menuSort,
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
            if (count($children) > 1) {
                $active = false;
                foreach ($children as $child) {
                    if ($child->LinkingMode == 'current') {
                        $active = true;
                    }
                }

                $icon = (array_key_exists($group, $menus) && array_key_exists('icon_class', $menus[$group]))
                    ? $menus[$group]['icon_class']
                    : 'far fa-folder-open';

                $code = str_replace(' ', '_', $group);

                $result->push(ArrayData::create([
                    'Title'       => _t('GroupedCmsMenuLabel.' . $code, $group),
                    'Code'        => DBField::create_field('Text', $code),
                    'Link'        => $children->First()->Link,
                    'Icon'        => $icon,
                    'LinkingMode' => $active ? 'current' : '',
                    'Children'    => $config->get('menu_groups_alphabetical_sorting')
                        ? $children->sort('Title')
                        : $children->sort('SortOrder'),
                ]));
            } else {
                $result->push($children->First());
            }
        }

        return $result;
    }
}
