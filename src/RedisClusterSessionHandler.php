<?php
namespace Webman;
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


class RedisClusterSessionHandler extends RedisSessionHandler
{
    public function __construct($config)
    {
        $this->_maxLifeTime = (int)ini_get('session.gc_maxlifetime');
        $timeout = $config['timeout'] ?? 2;
        $persistent = $config['persistent'] ?? false;
        $auth = $config['auth'] ?? '';
        $args = [null, $config['host'], $timeout, $timeout, $persistent];
        if ($auth) {
            $args[] = $auth;
        }
        $this->_redis = new \RedisCluster(...$args);
    }

}