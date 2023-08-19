<?php
/**
 * This file is part of webman.
 *
 * Licensed under The MIT License
 * For full copyright and license information, please see the MIT-LICENSE.txt
 * Redistributions of files must retain the above copyright notice.
 *
 * @author    chaz6chez<chaz6chez1993@outlook.com>
 * @link      http://www.workerman.net/
 * @license   http://www.opensource.org/licenses/mit-license.php MIT License
 */

namespace support;

use Workerman\Timer;

/**
 * Class LongPollingResponse
 * @package support
 */
class LongPollingResponse extends Response
{
    /** @var float 等待时长 */
    protected $_wait;
    /** @var Request 长轮询请求对象 */
    protected $_request;
    /** @var int 长轮询定时器计数 */
    protected static $_count = 0;

    /**
     * @param Request $request
     * @param int $status
     * @param array $headers
     * @param string $body
     * @param float $wait 等待时长
     */
    public function __construct(Request $request, $status = 200, $headers = [], string $body = '', float $wait = 0)
    {
        $this->_wait = $wait;
        $this->_request = $request;
        parent::__construct($status, $headers, $body);

        if ($this->_wait > 0) {
            Timer::add($wait, function () {
                self::$_count ++;
                \Webman\App::send($this->_request->connection, $this, $this->_request);
                self::$_count --;
            }, [], false);
        }
    }

    /**
     * @return int
     */
    public static function getLongPollingCount(): int
    {
        return self::$_count;
    }
}
