<?php
/**
 * This file is part of webman.
 *
 * Licensed under The MIT License
 * For full copyright and license information, please see the MIT-LICENSE.txt
 * Redistributions of files must retain the above copyright notice.
 *
 * @author    tangzhangming <tangzhangming@live.com>
 * @copyright tangzhangming <tangzhangming@live.com>
 * @link      http://www.workerman.net/
 * @license   http://www.opensource.org/licenses/mit-license.php MIT License
 */
namespace Webman\Auth;

interface IdentityInterface
{

    /**
     * 根据id 返回用户数据
     * 其格式并不限制为对象或数组
     * 可以使用 AuthManger::user() 访问到findIdentity的结果
     */
    public static function findIdentity($id);

    /**
     * 返回当前用户的 id
     */
    public function id();

}
