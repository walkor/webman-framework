<?php

namespace Webman;

class Install
{
    const WEBMAN_PLUGIN = true;

    /**
     * @var array
     */
    protected static $pathRelation = [
        'start.php' => 'start.php',
        'windows.php' => 'windows.php',
        'support/bootstrap.php' => 'support/bootstrap.php',
    ];

    /**
     * Install
     * @return void
     */
    public static function install()
    {
        static::installByRelation();
    }

    /**
     * Uninstall
     * @return void
     */
    public static function uninstall()
    {

    }

    /**
     * InstallByRelation
     * @return void
     */
    public static function installByRelation()
    {
        $basePath = realpath(__DIR__ . '/../../../../');
        foreach (static::$pathRelation as $source => $dest) {
            if ($pos = strrpos($dest, '/')) {
                $parentDir = $basePath . '/' . substr($dest, 0, $pos);
                if (!is_dir($parentDir)) {
                    mkdir($parentDir, 0777, true);
                }
            }
            $sourceFile = __DIR__ . "/$source";
            copy_dir($sourceFile, $basePath . "/$dest", true);
            echo "Create $dest\r\n";
            if (is_file($sourceFile)) {
                @unlink($sourceFile);
            }
        }
    }

}
