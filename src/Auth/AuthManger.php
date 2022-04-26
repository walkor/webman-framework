<?php
namespace Webman\Auth;

use Webman\App;

class AuthManger
{

	/**
	 * 登录页地址
	 */
    protected $login_url;

    /**
     * 登录成功后跳转地址
     */
    protected $return_url;

    /**
     * session中保存用户ID的键名
     */
    protected $user_id_key;

    /**
     * 当前用户的认证类
     */
    protected $identityClass;


    private $_identity;


    public function __construct($guard='web')
    {
    	$config = config("auth.guards.{$guard}");

    	//默认用户组不配置则使用默认配置
    	if( $guard=='web' && is_null($config) ){
    		$config = self::default();
    	}

    	if( $config && is_array($config) ){
    		foreach ($config as $key => $value) {
    			$this->$key = $value;
    		}
    	}
    }

    /**
     * 默认用户组配置
     */
    public static function default()
    {
    	return [
			'user_id_key'   => '6ac21fd8270b4f6d_id',
			'login_url'     => 'login',
			'return_url'    => '/?login_success',
			'identityClass' => '\app\model\User',
    	];
    }


    /**
     * 指定看守器
     */
    public function guard(string $guard)
    {
    	return (new self($guard));
    }

    /**
     * 返回当前看守器用户储存在session中的key
     */
    protected function get_login_id()
    {
    	return $this->user_id_key;
    }


	/**
	 * 登录
	 */
	public function login($user)
	{
		return App::request()->session()->set($this->get_login_id(), $user->id());
	}

	/**
	 * 退出
	 */
	public function logout()
	{
		return App::request()->session()->forget($this->get_login_id());
	}


	/**
	 * 是否为游客
	 * @return 布尔值
	 */
	public function IsGuest()
	{
		return $this->user() === null;
	}


	/**
	 * 强制登录 否则跳转至登录页面
	 * webman中无法使用redirect
	 */
	public function loginRequired()
	{
		if( $this->IsGuest() ){
			return redirect('/');
		}
	}

	/**
	 * 返回用户ID
	 */
	public function id()
	{
		if( $this->_identity == false ){
			$this->renewAuthStatus();
		}

		return $this->_identity->id();
	}

	/**
	 * 获取用户类实例
	 */
	public function user()
	{
		if( $this->_identity == false ){
			$this->renewAuthStatus();
		}
		
		return $this->_identity;
	}

	protected function renewAuthStatus()
	{
		$id = App::request()->session()->get($this->get_login_id());

		//读取用户信息
		if( $id == null ){
			$identity = null;
		}else{
            $class = $this->identityClass;
            $identity = $class::findIdentity($id);
		}


		//验证validateAuthKey
		if ($identity !== null){

		}

		$this->setIdentity($identity);
	}

	protected function setIdentity($identity)
	{
		if( $identity instanceof \Webman\Auth\IdentityInterface ){
			$this->_identity = $identity;

		}elseif ($identity === null){
			$this->_identity = null;

		}else{
			throw new \Exception("identity must implements from IdentityInterface");

		}
	}
}