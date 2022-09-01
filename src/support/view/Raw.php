<?php
/**
 * This file is part of webman.
 *
 * Licensed under The MIT License
 * For full copyright and license information, please see the MIT-LICENSE.txt
 * Redistributions of files must retain the above copyright notice.
 *
 * @author    walkor<walkor@workerman.net>
 * @copyright walkor<walkor@workerman.net>
 * @link      http://www.workerman.net/
 * @license   http://www.opensource.org/licenses/mit-license.php MIT License
 */

namespace support\view;

use Webman\View;
use Throwable;

/**
 * Class Raw
 * @package support\view
 */
class Raw implements View
{
    /**
     * @var array
     */
    protected static $_vars = [];

    /**
     * @param string|array $name
     * @param mixed $value
     */
    public static function assign($name, $value = null)
    {
        static::$_vars = \array_merge(static::$_vars, \is_array($name) ? $name : [$name => $value]);
    }

    /**
     * @param string $template
     * @param array $vars
     * @param string|null $app
     * @return false|string
     */
    public static function render(string $template, array $vars, string $app = null)
    {
        $request = \request();
        $plugin = $request->plugin ?? '';
        $config_prefix = $plugin ? "plugin.$plugin." : '';
        $view_suffix = \config("{$config_prefix}view.options.view_suffix", 'html');
        $app = $app === null ? $request->app : $app;
        $base_view_path = $plugin ? \base_path() . "/plugin/$plugin/app" : \app_path();
        $__template_path__ = $app === '' ? "$base_view_path/view/$template.$view_suffix" : "$base_view_path/$app/view/$template.$view_suffix";

        \extract(static::$_vars);
        \extract($vars);
        \ob_start();
        // Try to include php file.
        try {
            include $__template_path__;
        } catch (Throwable $e) {
            static::$_vars = [];
            \ob_end_clean();
            throw $e;
        }
        static::$_vars = [];
        return \ob_get_clean();
    }

}
