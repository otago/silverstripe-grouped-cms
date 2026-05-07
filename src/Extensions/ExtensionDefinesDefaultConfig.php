<?php

namespace Otago\GroupedCMS\Extensions;

use SilverStripe\Core\Config\Config;

/**
 * Extensions that define default config values with private static vars are higher-priority
 * than the same settings on the extended class — even when the intent is to let the project
 * override them. This trait restores the expected behaviour.
 *
 * The using class must define a const CONFIG_SETTINGS_WITH_DEFAULTS listing the setting names.
 */
trait ExtensionDefinesDefaultConfig
{
    public static function get_extra_config($class)
    {
        $config = Config::inst();
        $classConfig = $config->get($class, null, Config::EXCLUDE_EXTRA_SOURCES);
        $extensionConfig = $config->get(static::class);
        $extraConfig = [];

        foreach (static::CONFIG_SETTINGS_WITH_DEFAULTS as $setting) {
            if (isset($classConfig[$setting]) && !is_array($extensionConfig[$setting])) {
                $extraConfig[$setting] = $classConfig[$setting];
            } else {
                $extraConfig[$setting] = $extensionConfig[$setting];
            }
        }

        return $extraConfig;
    }
}
