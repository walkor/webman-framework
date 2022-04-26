<?php
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
