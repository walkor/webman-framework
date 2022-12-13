<?php

namespace support;

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
        $autoload = \method_exists($operation, 'getPackage') ? $operation->getPackage()->getAutoload() : $operation->getTargetPackage()->getAutoload();
        if (!isset($autoload['psr-4'])) {
            return;
        }
        foreach ($autoload['psr-4'] as $namespace => $path) {
            $install_function = "\\{$namespace}Install::install";
            $plugin_const = "\\{$namespace}Install::WEBMAN_PLUGIN";
            if (\defined($plugin_const) && \is_callable($install_function)) {
                $install_function();
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
            $uninstall_function = "\\{$namespace}Install::uninstall";
            $plugin_const = "\\{$namespace}Install::WEBMAN_PLUGIN";
            if (defined($plugin_const) && is_callable($uninstall_function)) {
                $uninstall_function();
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
        if (\is_file($file)) {
            require_once $file;
            return;
        }
        // Plugin.php in webman
        require_once __DIR__ . '/helpers.php';
    }

}
