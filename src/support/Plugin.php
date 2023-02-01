<?php

namespace support;

use function defined;
use function is_callable;
use function is_file;
use function method_exists;

class Plugin
{
    /**
     * Install.
     * @param $event
     * @return void
     */
    public static function install($event)
    {
        static::findHelper();
        $operation = $event->getOperation();
        $autoload = method_exists($operation, 'getPackage') ? $operation->getPackage()->getAutoload() : $operation->getTargetPackage()->getAutoload();
        if (!isset($autoload['psr-4'])) {
            return;
        }
        foreach ($autoload['psr-4'] as $namespace => $path) {
            $installFunction = "\\{$namespace}Install::install";
            $pluginConst = "\\{$namespace}Install::WEBMAN_PLUGIN";
            if (defined($pluginConst) && is_callable($installFunction)) {
                $installFunction();
            }
        }
    }

    /**
     * Update.
     * @param $event
     * @return void
     */
    public static function update($event)
    {
        static::install($event);
    }

    /**
     * Uninstall.
     * @param $event
     * @return void
     */
    public static function uninstall($event)
    {
        static::findHelper();
        $autoload = $event->getOperation()->getPackage()->getAutoload();
        if (!isset($autoload['psr-4'])) {
            return;
        }
        foreach ($autoload['psr-4'] as $namespace => $path) {
            $uninstallFunction = "\\{$namespace}Install::uninstall";
            $pluginConst = "\\{$namespace}Install::WEBMAN_PLUGIN";
            if (defined($pluginConst) && is_callable($uninstallFunction)) {
                $uninstallFunction();
            }
        }
    }

    /**
     * FindHelper.
     * @return void
     */
    protected static function findHelper()
    {
        // Plugin.php in vendor
        $file = __DIR__ . '/../../../../../support/helpers.php';
        if (is_file($file)) {
            require_once $file;
            return;
        }
        // Plugin.php in webman
        require_once __DIR__ . '/helpers.php';
    }

}
