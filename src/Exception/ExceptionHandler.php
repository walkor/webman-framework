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
namespace Webman\Exception;

use support\Request;
use Throwable;
use Psr\Log\LoggerInterface;

/**
 * Class Handler
 * @package support\exception
 */
class ExceptionHandler
{
    /**
     * @var LoggerInterface
     */
    protected $_logger = null;

    public $dontReport = [

    ];

    public function __construct($logger)
    {
        $this->_logger = $logger;
    }

    public function report(Throwable $exception)
    {
        if ($this->shouldntReport($exception)) {
            return;
        }

        $this->_logger->error($exception->getMessage(), ['exception' => (string)$exception]);
    }

    public function render(Request $request, Throwable $exception)
    {
        if (\method_exists($exception, 'render')) {
            return $exception->render();
        }
        $code = $exception->getCode();
        $debug = config('app.debug');
        if ($request->expectsJson()) {
            $json = ['code' => $code ? $code : 500, 'msg' => $exception->getMessage()];
            $debug && $json['traces'] = (string)$exception;
            return json($json,  JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        }
        $error = $debug ? (string)$exception : 'Server internal error';
        return response($error, 500);
    }

    protected function shouldntReport(Throwable $e) {
        foreach ($this->dontReport as $type) {
            if ($e instanceof $type) {
                return false;
            }
        }
        return false;
    }
}