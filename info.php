<?php

namespace sgpublic\scit\tool;

define('SCIT_EDU_TOOL_ROOT', dirname(__FILE__));
require SCIT_EDU_TOOL_ROOT.'/base/NormalAPI.php';
require SCIT_EDU_TOOL_ROOT.'/helper/InfoHelper.php';

use sgpublic\scit\tool\base\NormalAPI;
use sgpublic\scit\tool\core\Debug;
use sgpublic\scit\tool\core\Verify;
use sgpublic\scit\tool\helper\InfoHelper;
use sgpublic\scit\tool\helper\SessionHelperException;
use sgpublic\scit\tool\helper\TokenHelper;
use sgpublic\scit\tool\token\TokenChecker;

class info extends NormalAPI {
    public function __construct(array $args) {
        parent::__construct($args);
    }

    protected function API(Verify $sign){
        try {
            $helper = TokenHelper::getInterface($sign->getParameter('access_token'), 'access');
            if ($helper->check() == 0){
                $this->result = InfoHelper::getInterface($helper->getChecker())->get($info);
                if (!isset($this->result['info'])){
                    $this->result['info'] = null;
                }
                if ($this->result['code'] == 200) {
                    $info['faculty'] = $info['faculty']['name'];
                    $info['specialty'] = $info['specialty']['name'];
                    $info['class'] = $info['class']['name'];
                    $this->result['info'] = $info;
                } else if ($this->result['info'] == '账号或密码错误'){
                    Debug::getTrack(
                        $this->result, 504, '登录状态失效，请重新登陆',
                        __DIR__, __FILE__, __LINE__, __METHOD__
                    );
                } else if ($this->result['message'] == '请先完成教评'){
                    Debug::getTrack(
                        $this->result, 500, '请先完成教评',
                        __DIR__, __FILE__, __LINE__, __METHOD__,
                        $this->result['info']
                    );
                } else {
                    Debug::getTrack(
                        $this->result, 502, $this->result['message'],
                        __DIR__, __FILE__, __LINE__, __METHOD__,
                        $this->result['info']
                    );
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

new info([
    'access_token' => isset($_COOKIE['access_token']) ? $_COOKIE['access_token'] : null
]);