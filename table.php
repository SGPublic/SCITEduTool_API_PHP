<?php

namespace sgpublic\scit\tool;

define('SCIT_EDU_TOOL_ROOT', dirname(__FILE__));
require SCIT_EDU_TOOL_ROOT.'/base/NormalAPI.php';
require SCIT_EDU_TOOL_ROOT."/helper/TableHelper.php";
require SCIT_EDU_TOOL_ROOT."/helper/InfoHelper.php";

use sgpublic\scit\tool\base\NormalAPI;
use sgpublic\scit\tool\core\Debug;
use sgpublic\scit\tool\core\Verify;
use sgpublic\scit\tool\helper\SessionHelperException;
use sgpublic\scit\tool\helper\TableHelper;
use sgpublic\scit\tool\helper\TokenHelper;

class table extends NormalAPI {

    public function __construct(array $args) {
        parent::__construct($args);
    }

    protected function API(Verify $sign){
        try {
            $helper = TokenHelper::getInterface($sign->getParameter('access_token'), 'access');
            if ($helper->check() == 0){
                $this->result = TableHelper::getInterface($helper->getChecker())->get(
                    $sign->getParameter('year'),
                    intval($sign->getParameter('semester')),
                    $table
                );
                if ($this->result['code'] == 200) {
                    $this->result['table'] = $table;
                } else if ($this->result['message'] == '账号或密码错误'){
                    Debug::getTrack(
                        $this->result, 504, '登录状态失效，请重新登陆',
                        __DIR__, __FILE__, __LINE__, __METHOD__,
                        '账号或密码错误'
                    );
                } else {
                    Debug::getTrack(
                        $this->result, 502, '服务器内部错误',
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

new table([
    'year' => null,
    'semester' => null,
    'access_token' => isset($_COOKIE['access_token']) ? $_COOKIE['access_token'] : null
]);