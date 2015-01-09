<?php
/**
 * 用户登录,注册等接口
 *
 * 注意：客户端在请求时需要携带cookie <br>
 * csrf_token=pw;PHPSESSID=guid <br>
 * authOpenAccountAction() <br>
 * openAccountRegisterAction() <br>
 * openAccountLoginAction() <br>
 *
 * @fileName: UserController.php
 * @author: dongyong<dongyong.ydy@alibaba-inc.com>
 * @license: http://www.phpwind.com
 * @version: $Id
 * @lastchange: 2014-12-15 19:10:43
 * @desc: 
 **/
defined('WEKIT_VERSION') || exit('Forbidden');

Wind::import('SRV:user.srv.PwRegisterService');
Wind::import('SRV:user.srv.PwLoginService');
Wind::import('APPS:native.controller.NativeBaseController');

class UserController extends NativeBaseController {

	public function beforeAction($handlerAdapter) {
		parent::beforeAction($handlerAdapter);
	}

    /**
     * 校验用户是否登录; 返回appid接口数据
     * 
     * @access public
     * @return void
     * @example
     <pre>
     /index.php?m=native&c=user&a=checkLoginStatus
     <br>
     post: securityKey <br>
     response: {"referer":"","refresh":false,"state":"success","data":{"thirdPlatformAppid":{"taobao":{"order":"0","appId":"a123456"}},"userinfo":{"username":"qiwen","avatar":"http:\/\/img1.phpwind.net\/attachout\/avatar\/002\/37\/41\/2374101_small.jpg","gender":0}},"html":"","message":["\u6b22\u8fce\u56de\u6765..."],"__error":""}
     </pre>
     */
    public function checkLoginStatusAction(){
        $data['thirdPlatformAppid'] = $this->thirdPlatformAppid();
        if( $this->isLogin() ){
            $data = array_merge($this->_getUserInfo(),$data) ;
            //
            $this->setOutput($data, 'data');
            $this->showMessage('USER:login.success');
        }
        $this->setOutput($data, 'data');
        $this->showMessage('USER:login.success');
    } 

    /**
     * 登录;并校验验证码
     * @access public
     * @return string
     * @example
     <pre>
     /index.php?m=native&c=user&a=doLogin <br>
     post: username&password&csrf_token&code&_json=1 <br>
     response: 
    {
        "referer": "",
            "refresh": false,
            "state": "fail",
            "message": [
                "帐号不存在"

            ],
        "__error": ""
    }
     </pre>
     */
    public function doLoginAction(){
        
        list($username, $password, $code) = $this->getInput(array('username', 'password', 'code'));
        
        if (empty($username) || empty($password)) $this->showError('USER:login.user.require');

        //
        if( $this->_showVerify() ){
            $veryfy = $this->_getVerifyService();                                                                                                         
            if ($veryfy->checkVerify($code) !== true) {
                $this->showError('USER:verifycode.error');
            }        
        }

        /* [验证用户名和密码是否正确] */
        $login = new PwLoginService();
        $this->runHook('c_login_dorun', $login);

        $isSuccess = $login->login($username, $password, $this->getRequest()->getClientIp());
        if ($isSuccess instanceof PwError) {
            $this->showError($isSuccess->getError());
        }

        //
        Wind::import('SRV:user.srv.PwRegisterService');
        $registerService = new PwRegisterService();
        $info = $registerService->sysUser($isSuccess['uid']);
        if (!$info)  $this->showError('USER:user.syn.error');

        //
        $this->uid=$isSuccess['uid'];
        $this->setOutput( $this->_getUserInfo(), 'data');
        $this->showMessage('USER:login.success');
    }

    /**
     * 注册帐号 
     * @access public
     * @return void
     * @example
     <pre>
     /index.php?m=native&c=user&a=doRegister    <br>
     post: username&password&repassword&email&code 
     response: {err:"",data:""} 
     </pre>
     */
    public function doRegisterAction(){
        list($username,$password,$email,$code) = $this->getInput(array('username','password','email','code'));

        //  验证输入
        Wind::import('Wind:utility.WindValidator');
        $config = $this->_getRegistConfig();
        if (!$username) $this->showError('USER:user.error.-1');
        if (!$password) $this->showError('USER:pwd.require');
        if (!$email) $this->showError('USER:user.error.-6');
        if (!WindValidator::isEmail($email)) $this->showError('USER:user.error.-7');
	
		foreach ($config['active.field'] as $field) {
			if (!$this->getInput($field, 'post')) $this->showError('USER:register.error.require.needField.' . $field);
		}
		if ($config['active.check'] && !$regreason) {
			$this->showError('USER:register.error.require.regreason');
		}

        if( $this->_showVerify() ){
            $veryfy = $this->_getVerifyService();                                                                                                         
            if ($veryfy->checkVerify($code) !== true) {
                $this->showError('USER:verifycode.error');
            }        
        }

        Wind::import('SRC:service.user.dm.PwUserInfoDm');
        $userDm = new PwUserInfoDm();
        $userDm->setUsername($username);
        $userDm->setPassword($password);
        $userDm->setEmail($email);
        $userDm->setRegdate(Pw::getTime());
        $userDm->setLastvisit(Pw::getTime());
        $userDm->setRegip(Wind::getComponent('request')->getClientIp());

        $userDm->setAliww($aliww);
        $userDm->setQq($qq);
        $userDm->setMsn($msn);
        $userDm->setMobile($mobile);
        $userDm->setMobileCode($mobileCode);
        $userDm->setQuestion($question, $answer);
        $userDm->setRegreason($regreason);

        $areaids = array($hometown, $location);
        if ($areaids) {
            $srv = WindidApi::api('area');
            $areas = $srv->fetchAreaInfo($areaids);
            $userDm->setHometown($hometown, isset($areas[$hometown]) ? $areas[$hometown] : '');
            $userDm->setLocation($location, isset($areas[$location]) ? $areas[$location] : '');
        }

        //
		$registerService = new PwRegisterService();
		$registerService->setUserDm( $userDm );
		/*[u_regsiter]:插件扩展*/
		$this->runHook('c_register', $registerService);
		if (($info = $registerService->register()) instanceof PwError) {
			$this->showError($info->getError());
        } else {
            if (1 == Wekit::C('register', 'active.mail')) {
                $this->showMessage('USER:active.sendemail.success');
            } else {
                $this->uid = $info['uid'];
                $this->setOutput($this->_getUserInfo(), 'data');                                                                                       
                $this->showMessage('USER:register.success');
			}
		}
    }

    /**
     * 开放帐号登录; (通过第三方开放平台认证通过后,获得的帐号id在本地查找是否存在,如果存在登录成功 ) 
     * 如果没绑定第三方账号；结果不返回securityKey，则返回第三方账号用户信息；否则返回论坛账号信息
     * @access public
     * @return string sessionid
     * @example
     <pre>
     post: access_token&platformname(qq|weibo|weixin|taobao)&native_name(回调地址)
     </pre>
     */
    public function openAccountLoginAction(){
        $accountData=$this->authThirdPlatform();
        //
        $accountRelationData = $this->_getUserOpenAccountDs()->getUid($accountData['uid'],$accountData['type']);
        //还没有绑定帐号
        if( empty($accountRelationData) ){
            $userdata = array(
                //'securityKey'=>null,
                'userinfo'=>$accountData,
            );
        }else{
            /* [验证用户名和密码是否正确] */
            $login = new PwLoginService();
            $this->runHook('c_login_dorun', $login);

            Wind::import('SRV:user.srv.PwRegisterService');
            $registerService = new PwRegisterService();
            $info = $registerService->sysUser($accountRelationData['uid']);
            if (!$info) {
                $this->showError('USER:user.syn.error');
            }
            $this->uid=$info['uid'];
            $userdata = $this->_getUserInfo();
        }
        //success
        $this->setOutput($userdata,'data');
        $this->showMessage('USER:login.success');
    }

    /**
     * 开放帐号注册到本系统内
     *
     * @access public
     * @return void
     * @example
     <pre>
     post: access_token&platformname&native_name&username&email&sex
     </pre>
     */
    public function openAccountRegisterAction() {
        $accountData=$this->authThirdPlatform();
        // 
        list($username,$email,$sex) = $this->getInput(array('username','email','sex'));
        //随机密码
        $password = substr(str_shuffle('abcdefghigklmnopqrstuvwxyz1234567890~!@#$%^&*()'),0,7);
        //
        Wind::import('SRC:service.user.dm.PwUserInfoDm');
        $userDm = new PwUserInfoDm();
        $userDm->setUsername($username);
        $userDm->setPassword($password);
        $userDm->setEmail($email);
        $userDm->setGender($sex);
        $userDm->setRegdate(Pw::getTime());
        $userDm->setLastvisit(Pw::getTime());
        $userDm->setRegip(Wind::getComponent('request')->getClientIp());

        //
        $registerService = new PwRegisterService();
        $registerService->setUserDm( $userDm );
        /*[u_regsiter]:插件扩展*/
        $this->runHook('c_register', $registerService);
        if (($info = $registerService->register()) instanceof PwError) {
            $this->showError($info->getError());
        } else {
            //这里注册成功，要把第三方帐号的头像下载下来并处理，这里还没有做
            if( $this->_getUserOpenAccountDs()->addUser($info['uid'],$accountData['uid'],$accountData['type'])==false ){
                $this->downloadThirdPlatformAvatar($info['uid'],$accountData['avatar']);
                //
                $this->uid=$info['uid'];
                $userdata = $this->_getUserInfo($info['uid']);
                $this->setOutput($userdata,'data');
                $this->showMessage('USER:register.success');
            }
        }
    }

    /**
     * 修改头像 
     * @access public
     * @return void
     * @example
     <pre>
     /index.php?m=native&c=user&a=doAvatar <br>
     post: securityKey <br>
     postdata: Filename <br>
     curl -X POST -F 'Filename=@icon1.jpg' -F 'csrf_token=aaa' -F '_json=1' -F 'securityKey=xx' -b 'csrf_token=aaa' '/index.php?m=native&c=user&a=doAvatar'
     </pre>
     */
    public function doAvatarAction(){
        $this->checkUserSessionValid();
        //
        Wind::import('WSRV:upload.action.WindidAvatarUpload');
        Wind::import('LIB:upload.PwUpload');
        $bhv = new WindidAvatarUpload($this->uid);

        $upload = new PwUpload($bhv);
        if (($result = $upload->check()) === true) {
            foreach ($_FILES as $key => $value) {
                if (!PwUpload::isUploadedFile($value['tmp_name']) || !$bhv->allowType($key)) {
                    continue;
                }   
            }
            $file = new PwUploadFile($key, $value);
            if (($result = $upload->checkFile($file)) !== true) {
                $this->showError($result->getError());
            }
            $file->filename = $upload->filterFileName($bhv->getSaveName($file));
            $file->savedir = $bhv->getSaveDir($file);
            $file->store = Wind::getComponent($bhv->isLocal ? 'localStorage' : 'storage');
            $file->source = str_replace('attachment','windid/attachment',$file->store->getAbsolutePath($file->filename, $file->savedir) );
            //
            if (!PwUpload::moveUploadedFile($value['tmp_name'], $file->source)) {
                $this->showError('upload.fail');
            }

            $image = new PwImage($file->source);
            if ($bhv->allowThumb()) {
                $thumbInfo = $bhv->getThumbInfo($file->filename, $file->savedir);
                foreach ($thumbInfo as $key => $value) {
                    $thumburl = $file->store->getAbsolutePath($value[0], $value[1]); 
                    $thumburl = str_replace('attachment','windid/attachment',$thumburl);
                    //
                    $result = $image->makeThumb($thumburl, $value[2], $value[3], $quality, $value[4], $value[5]);
                    if ($result === true && $image->filename != $thumburl) {
                        $ts = $image->getThumb();
                    }
                } 
            }
            $this->showMessage('success');
        }
        $this->showMessage('operate.fail');
    }

    /**
     * 修改性别 
     * @access public
     * @return void
     * @example
     <pre>
     /index.php?m=native&c=user&a=doSex <br>
     post: securityKey&gender
     </pre>
     */
    public function doSexAction(){
        $this->checkUserSessionValid();
        // 
        $userDm = new PwUserInfoDm($this->uid);
        $userDm->setGender($this->getInput('gender', 'post'));

        /* @var $userDs PwUser */
        $result = $this->_getUserDs()->editUser($userDm, PwUser::FETCH_MAIN + PwUser::FETCH_INFO);

        if ($result instanceof PwError) {
            $this->showError($result->getError());
        }else{
            PwSimpleHook::getInstance('profile_editUser')->runDo($dm);
            $this->showMessage('USER:user.edit.profile.success');
        }
    }

    /**
     * 保存修改密码 
     * @access public
     * @return void
     * @example
     <pre>
     /index.php?m=native&c=user&a=doPassWord <br>
     post: securityKey&oldPwd&newPwd&rePwd
     </pre>
     */
    public function doPasswordAction(){
        $this->checkUserSessionValid();
        //
        list($newPwd, $oldPwd, $rePwd) = $this->getInput(array('newPwd', 'oldPwd', 'rePwd'), 'post');
        if (!$oldPwd) {
            $this->showError('USER:pwd.change.oldpwd.require');
        }   
        if (!$newPwd) {
            $this->showError('USER:pwd.change.newpwd.require');
        }   
        if ($rePwd != $newPwd) {
            $this->showError('USER:user.error.-20');
        }   
        $this->checkOldPwd($this->uid, $oldPwd);

        Wind::import('SRC:service.user.dm.PwUserInfoDm');
        $userDm = new PwUserInfoDm($this->uid);
        $userDm->setPassword($newPwd);
        $userDm->setOldPwd($oldPwd);
        /* @var $userDs PwUser */
        $userDs = Wekit::load('user.PwUser');
        if (($result = $userDs->editUser($userDm, PwUser::FETCH_MAIN)) instanceof PwError) {
            $this->showError($result->getError());

        }
        //$userdata = $this->_getUserInfo($this->uid);
        //$this->setOutput($userdata,'data');
        $this->showMessage('USER:pwd.change.success');
    }

    /**
     * 是否需要显示验证码 <br>
     * 需要cookie携带 PHPSESSID <br>
     * /index.php?m=verify&a=get&rand=rand()
     * 
     * @access public
     * @return boolean
     * @example
     * <pre>  
     * /index.php?m=native&c=user&a=ifshowVerifycode <br>
     * </pre>
     */
    public function ifShowVerifycodeAction(){
        $this->setOutput($this->_showVerify(), 'data');
        $this->showMessage('success');
    }

    /**
     * 判断是否需要展示验证码
     * 
     * @return boolean
     */
    private function _showVerify() {
        return $result = false;
        //
        $config = Wekit::C('verify', 'showverify');
        !$config && $config = array();
        if(in_array('userlogin', $config)==true){
            $result = true;
        }else{
            //ip限制,防止撞库; 错误三次,自动显示验证码
            $ipDs = Wekit::load('user.PwUserLoginIpRecode');
            $info = $ipDs->getRecode($this->getRequest()->getClientIp());
            $result = is_array($info) && $info['error_count']>3 ? true : false;
        }
        return $result;
    }


    /**
     * 开放平台帐号关联ds
     * 
     * @access private
     * @return void
     */
    private function _getUserOpenAccountDs() {
        return Wekit::load('native.PwOpenAccount');
    }   


}