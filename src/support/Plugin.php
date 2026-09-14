<?php

namespace support;

use function current;
use function defined;
use function is_array;
use function is_callable;
use function is_file;
use function method_exists;

class Plugin
{
    /**
     * Install.
     * @param mixed $event
     * @return void
     */
    public static function install($event)
    {
        static::findHelper();
        $psr4 = static::getPsr4($event);
        foreach ($psr4 as $namespace => $path) {
            static::findInstallFile($event, $path);
            $pluginConst = "\\{$namespace}Install::WEBMAN_PLUGIN";
            if (!defined($pluginConst)) {
                continue;
            }
            $installFunction = "\\{$namespace}Install::install";
            if (is_callable($installFunction)) {
                $installFunction(true);
            }
        }
    }

    /**
     * Update.
     * @param mixed $event
     * @return void
     */
    public static function update($event)
    {
        static::findHelper();
        $psr4 = static::getPsr4($event);
        foreach ($psr4 as $namespace => $path) {
            static::findInstallFile($event, $path);
            $pluginConst = "\\{$namespace}Install::WEBMAN_PLUGIN";
            if (!defined($pluginConst)) {
                continue;
            }
            $updateFunction = "\\{$namespace}Install::update";
            if (is_callable($updateFunction)) {
                $updateFunction();
                continue;
            }
            $installFunction = "\\{$namespace}Install::install";
            if (is_callable($installFunction)) {
                $installFunction(false);
            }
        }
    }

    /**
     * Uninstall.
     * @param mixed $event
     * @return void
     */
    public static function uninstall($event)
    {
        static::findHelper();
        $psr4 = static::getPsr4($event);
        foreach ($psr4 as $namespace => $path) {
            static::findInstallFile($event, $path);
            $pluginConst = "\\{$namespace}Install::WEBMAN_PLUGIN";
            if (!defined($pluginConst)) {
                continue;
            }
            $uninstallFunction = "\\{$namespace}Install::uninstall";
            if (is_callable($uninstallFunction)) {
                $uninstallFunction();
            }
        }
    }

    /**
     * FindInstallFile.
     * @param mixed $event
     * @param mixed $path
     * @return void
     */
    protected static function findInstallFile($event, $path)
    {
        $operation = $event->getOperation();
        $package = method_exists($operation, 'getPackage') ? $operation->getPackage() : $operation->getTargetPackage();
        $srcPath = is_array($path) ? current($path) : $path;
        $file = base_path() . "/vendor/{$package->getName()}/$srcPath/Install.php";
        if (is_file($file)) {
            require_once $file;
        }
    }

    /**
     * Get psr-4 info
     *
     * @param mixed $event
     * @return array
     */
    protected static function getPsr4($event)
    {
        $operation = $event->getOperation();
        $autoload = method_exists($operation, 'getPackage') ? $operation->getPackage()->getAutoload() : $operation->getTargetPackage()->getAutoload();
        return $autoload['psr-4'] ?? [];
    }

    /**
     * FindHelper.
     * @return void
     */
    protected static function findHelper()
    {
        // Plugin.php in webman
        require_once __DIR__ . '/helpers.php';
    }
}
