<?php

namespace sgpublic\scit\tool;

define('SCIT_EDU_TOOL_ROOT', dirname(__FILE__));
require SCIT_EDU_TOOL_ROOT.'/base/NormalAPI.php';

use sgpublic\scit\tool\base\NormalAPI;
use sgpublic\scit\tool\core\Debug;
use sgpublic\scit\tool\core\Verify;
use sgpublic\scit\tool\helper\TokenHelper;
use sgpublic\scit\tool\token\TokenChecker;

class token extends NormalAPI {
    public function __construct(array $args) {
        parent::__construct($args);
    }

    protected function API(Verify $sign) {
        $helper = TokenHelper::getInterface($sign->getParameter('access_token'), TokenChecker::$ACCESS);
        switch ($helper->check()){
            case 0:
            case -1:
                $helper = TokenHelper::getInterface($sign->getParameter('refresh_token'), TokenChecker::$REFRESH);
                if ($helper->check() == 0){
                    $this->result = $helper->refresh($sign->getParameter('refresh_token'));
                } else {
                    Debug::getTrack(
                        $this->result, 504, 'refresh_token无效',
                        __DIR__, __FILE__, __LINE__, __METHOD__
                    );
                }
                break;
            case -4:
                Debug::getTrack(
                    $this->result, 504, '登录状态失效，请重新登陆',
                    __DIR__, __FILE__, __LINE__, __METHOD__,
                    $helper->check()
                );
                break;
            default:
                Debug::getTrack(
                    $this->result, 504, 'access_token无效',
                    __DIR__, __FILE__, __LINE__, __METHOD__,
                    $helper->check()
                );
        }
    }
}

new token([
    'access_token' => isset($_COOKIE['access_token']) ? $_COOKIE['access_token'] : null,
    'refresh_token' => isset($_COOKIE['refresh_token']) ? $_COOKIE['refresh_token'] : null
]);
