<?php
namespace support;

use Webman\Auth\AuthManger;

class Auth
{

	public static function __callStatic($method, $args)
	{
		$request = request();
		if( !$request->auth_instance ){
			$request->auth_instance = new AuthManger();
		}
		$instance = $request->auth_instance ;


		if (! $instance) {
		  	throw new RuntimeException('未获取到 Webman\Auth\AuthManger 的实例');
		}

		return $instance->$method(...$args);
	}

}
