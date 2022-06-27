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

namespace Support\View;

use Webman\View;

/**
 * Class Raw
 * @package Support\View
 */
class Raw implements View
{
    /**
     * @var array
     */
    protected static $_vars = [];

    /**
     * @param $name
     * @param null $value
     */
    public static function assign($name, $value = null)
    {
        static::$_vars = \array_merge(static::$_vars, \is_array($name) ? $name : [$name => $value]);
    }

    /**
     * @param $template
     * @param $vars
     * @param null $app
     * @return string
     */
    public static function render($template, $vars, $app = null)
    {
        $request = request();
        $config_prefix = $request->plugin ? "plugin.{$request->plugin}." : '';
        $view_suffix = \config("{$config_prefix}view.options.view_suffix", 'html');
        $app = $app === null ? $request->app : $app;
        $base_view_path = $request->plugin ? \base_path() . "/plugin/{$request->plugin}/app" : \app_path();
        $view_path = $app === '' ? "$base_view_path/view/$template.$view_suffix" : "$base_view_path/$app/view/$template.$view_suffix";

        \extract(static::$_vars, \EXTR_SKIP);
        \extract($vars, \EXTR_SKIP);
        \ob_start();
        // Try to include php file.
        try {
            include $view_path;
        } catch (\Throwable $e) {
            static::$_vars = [];
            \ob_end_clean();
            throw $e;
        }
        static::$_vars = [];
        return \ob_get_clean();
    }

}
