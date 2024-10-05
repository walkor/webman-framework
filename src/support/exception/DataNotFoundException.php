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

namespace support\Exception;

use Throwable;

/**
 * Class BusinessException
 * @package support\exception
 */
class DataNotFoundException extends BusinessException
{
    public function __construct($message = 'Data not found', $code = 404, Throwable $previous = null) {
        parent::__construct($message, $code, $previous);
    }

    public function setModel(string $model, array $ids) {
        $this->message = "Data not found for model $model with id " . implode(', ', $ids);
        return $this;
    }
}
