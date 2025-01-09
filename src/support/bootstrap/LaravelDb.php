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

namespace support\bootstrap;

use Webman\Bootstrap;
use Webman\Database\Initializer;
use Workerman\Worker;
/**
 * Class Laravel
 * @package support\Bootstrap
 */
class LaravelDb implements Bootstrap
{
    /**
     * @param Worker|null $worker
     *
     * @return void
     */
    public static function start(?Worker $worker)
    {
        if (!class_exists(Initializer::class)) {
            return;
        }
        Initializer::init(config('database', []));
    }
}
