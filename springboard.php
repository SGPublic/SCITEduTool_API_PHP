<?php


namespace sgpublic\scit\tool;

define('SCIT_EDU_TOOL_ROOT', dirname(__FILE__));
require SCIT_EDU_TOOL_ROOT."/base/NormalAPI.php";

use sgpublic\scit\tool\base\NormalAPI;
use sgpublic\scit\tool\core\Debug;
use sgpublic\scit\tool\core\Verify;
use sgpublic\scit\tool\helper\SessionHelper;
use sgpublic\scit\tool\helper\SessionHelperException;
use sgpublic\scit\tool\helper\TokenHelper;

class springboard extends NormalAPI {
    public function __construct(array $args) {
        parent::__construct($args);
    }

    protected function API(Verify $sign) {
        try {
            $helper = TokenHelper::getInterface($sign->getParameter('access_token'), 'access');
            if ($helper->check() == 0){
                $this->result = SessionHelper::getInterfaceByTokenChecker($helper->getChecker())
                    ->getVerifyLocation($location);
                if ($this->result['code'] == 200){
                    $this->result['location'] = $location;
                }
            } else if ($helper->check() == -4) {
                Debug::getTrack(
                    $this->result, 504, '登录状态失效，请重新登陆',
                    __DIR__, __FILE__, __LINE__, __METHOD__
                );
            } else {
                Debug::getTrack(
                    $this->result, 504, 'Token无效',
                    __DIR__, __FILE__, __LINE__, __METHOD__
                );
            }
        } catch (SessionHelperException $e) {
            Debug::getTrack(
                $this->result, 500, '服务器内部错误',
                __DIR__, __FILE__, __LINE__, __METHOD__,
                $e->getMessage()
            );
        }
    }
}

new springboard([
    'access_token' => isset($_COOKIE['access_token']) ? $_COOKIE['access_token'] : null
]);