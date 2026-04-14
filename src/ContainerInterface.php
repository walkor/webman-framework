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

use Webman\Exception\NotFoundException;
use Psr\Container\ContainerInterface as PsrInterface;

interface ContainerInterface extends PsrInterface
{
    /**
     * Make.
     * @param string $name
     * @param array $constructor
     * @return mixed
     * @throws NotFoundException
     */
    public function make(string $name, array $constructor = []);


    /**
     * AddDefinitions.
     * @param array $definitions
     * @return $this
     */
    public function addDefinitions(array $definitions): static;

}
