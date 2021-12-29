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
namespace Webman;

use function FastRoute\TestFixtures\empty_options_cached;

class Config
{

    /**
     * @var array
     */
    protected static $_config = [];

    /**
     * @param $config_path
     * @param array $exclude_file
     */
    public static function load($config_path, $exclude_file = [])
    {
        if (\strpos($config_path, 'phar://') === false) {
            $config_path = realpath($config_path);
            if (!$config_path) {
                return;
            }
            $dir_iterator = new \RecursiveDirectoryIterator($config_path);
            $iterator = new \RecursiveIteratorIterator($dir_iterator);
            foreach ($iterator as $file) {
                /** var SplFileInfo $file */
                if (is_dir($file) || $file->getExtension() != 'php' || \in_array($file->getBaseName('.php'), $exclude_file)) {
                    continue;
                }
                $app_config_file = $file->getPath().'/app.php';
                if (!is_file($app_config_file)) {
                    continue;
                }
                $relative_path = str_replace($config_path . DIRECTORY_SEPARATOR, '', substr($file, 0, -4));
                $explode = array_reverse(explode(DIRECTORY_SEPARATOR, $relative_path));
                if (count($explode) >= 2) {
                    $app_config = include $app_config_file;
                    if (empty($app_config['enable'])) {
                        continue;
                    }
                }
                $config = include $file;
                foreach ($explode as $section) {
                    $tmp = [];
                    $tmp[$section] = $config;
                    $config = $tmp;
                }
                static::$_config = array_merge_recursive(static::$_config, $config);
            }

            // Merge database config
            foreach (static::$_config['ext']??[] as $name => $project) {
                foreach ($project['database']['connections']??[] as $key => $connection) {
                    static::$_config['database']['connections']["ext.$name.$key"] = $connection;
                }
            }
            // Merge thinkorm config
            foreach (static::$_config['ext']??[] as $name => $project) {
                foreach ($project['thinkorm']['connections']??[] as $key => $connection) {
                    static::$_config['thinkorm']['connections']["ext.$name.$key"] = $connection;
                }
            }
            if (!empty(static::$_config['thinkorm']['connections'])) {
                static::$_config['thinkorm']['default'] = static::$_config['thinkorm']['default'] ?? key(static::$_config['thinkorm']['connections']);
            }
            // Merge redis config
            foreach (static::$_config['ext']??[] as $name => $project) {
                foreach ($project['redis']??[] as $key => $connection) {
                    static::$_config['redis']["ext.$name.$key"] = $connection;
                }
            }
        } else {
            $handler = \opendir($config_path);
            while (($filename = \readdir($handler)) !== false) {
                if ($filename != "." && $filename != "..") {
                    $basename = \basename($filename, ".php");
                    if (\in_array($basename, $exclude_file)) {
                        continue;
                    }
                    $config = include($config_path . '/' . $filename);
                    static::$_config[$basename] = $config;
                }
            }
            \closedir($handler);
        }
    }

    /**
     * @param null $key
     * @param null $default
     * @return array|mixed|null
     */
    public static function get($key = null, $default = null)
    {
        if ($key === null) {
            return static::$_config;
        }
        $key_array = \explode('.', $key);
        $value = static::$_config;
        foreach ($key_array as $index) {
            if (!isset($value[$index])) {
                return $default;
            }
            $value = $value[$index];
        }
        return $value;
    }

    /**
     * @param $config_path
     * @param array $exclude_file
     */
    public static function reload($config_path, $exclude_file = [])
    {
        static::$_config = [];
        static::load($config_path, $exclude_file);
    }
}
