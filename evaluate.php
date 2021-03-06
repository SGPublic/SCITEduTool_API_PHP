<?php


namespace sgpublic\scit\tool;

define('SCIT_EDU_TOOL_ROOT', dirname(__FILE__));
require SCIT_EDU_TOOL_ROOT.'/base/NormalAPI.php';
require SCIT_EDU_TOOL_ROOT.'/helper/EvaluateHelper.php';

use sgpublic\scit\tool\base\NormalAPI;
use sgpublic\scit\tool\core\Debug;
use sgpublic\scit\tool\core\Verify;
use sgpublic\scit\tool\helper\EvaluateHelper;
use sgpublic\scit\tool\helper\SessionHelperException;
use sgpublic\scit\tool\helper\TokenHelper;

class evaluate extends NormalAPI {
    public function __construct(array $args) {
        parent::__construct($args);
    }

    protected function API(Verify $sign) {
        try {
            $helper = TokenHelper::getInterface($sign->getParameter('access_token'), 'access');
            if ($helper->check() == 0){
                $helper = EvaluateHelper::getInterface(
                    $helper->getChecker()
                );
                $action = $sign->getParameter("action");
                $index = intval($sign->getParameter("index")) - 1;
                if ($helper->open){
                    if ($action == "get") {
                        $this->result = $helper->get($index);
                        $this->result = array_diff_key($this->result, ['count' => array()]);
                    } else if ($action == "submit"){
                        $this->result = $helper->post($index, $sign->getParameter("data"));
                        $this->result = array_diff_key($this->result, ['count' => array()]);
                    } else {
                        $this->result = $helper->getTotalCount();
                    }
                } else {
                    Debug::getTrack(
                        $this->result, 404, '教评未开放',
                        __DIR__, __FILE__, __LINE__, __METHOD__
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

new evaluate([
    'access_token' => isset($_COOKIE['access_token']) ? $_COOKIE['access_token'] : null,
    'action' => 'check',
    'index' => 1,
    'data' => []
]);