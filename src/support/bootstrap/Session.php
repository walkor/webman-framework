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
use Workerman\Protocols\Http;
use Workerman\Protocols\Http\Session as SessionBase;
use Workerman\Worker;

/**
 * Class Session
 * @package support
 */
class Session implements Bootstrap
{

    /**
     * @param Worker $worker
     * @return void
     */
    public static function start($worker)
    {
        $config = config('session');
        Http::sessionName($config['session_name']);
        SessionBase::handlerClass($config['handler'], $config['config'][$config['type']]);
        if (property_exists(SessionBase::class, 'lifetime')) {
            $map = [
                'cookie_lifetime' => 'cookieLifetime',
                'gc_probability' => 'gcProbability',
                'cookie_path' => 'cookiePath',
                'lifetime' => 'lifetime',
                'http_only' => 'httpOnly',
                'domain' => 'domain',
                'secure' => 'secure',
                'same_site' => 'sameSite',
            ];
            foreach ($map as $key => $name) {
                if (isset($config[$key])) {
                    SessionBase::${$name} = $config[$key];
                }
            }
        }
    }
}