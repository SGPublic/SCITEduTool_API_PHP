<?php

namespace sgpublic\scit\tool\base;

header('Content-Type: application/json; charset=utf8');
if (isset($_SERVER['HTTP_ORIGIN'])){
    header('Access-Control-Allow-Origin: '.$_SERVER['HTTP_ORIGIN']);
} else {
    header('Access-Control-Allow-Origin: *');
}
header('Access-Control-Allow-Credentials: true');

require SCIT_EDU_TOOL_ROOT.'/unit/CurlUnit.php';
require SCIT_EDU_TOOL_ROOT.'/core/Debug.php';
require SCIT_EDU_TOOL_ROOT.'/manager/SignManager.php';
require SCIT_EDU_TOOL_ROOT.'/core/Verify.php';
require SCIT_EDU_TOOL_ROOT.'/core/SQLStaticUnit.php';
require SCIT_EDU_TOOL_ROOT.'/helper/SessionHelper.php';
require SCIT_EDU_TOOL_ROOT.'/helper/TokenHelper.php';

use sgpublic\scit\tool\core\Debug;
use sgpublic\scit\tool\core\Verify;
use sgpublic\scit\tool\helper\SignManager;

abstract class NormalAPI {
    private Verify $sign;
    protected array $result = [];

    protected function __construct(array $args) {
        $this->sign = new Verify();
        $this->sign->insert($args);
        if (!Debug::isDebug()){
            $app_key = $this->sign->getParameter('app_key');
            $platform = $this->sign->getParameter('platform');
            if ($app_key == null){
                $secret_available = SignManager::getDefaultAppSecretByPlatform($platform, $app_secret);
            } else {
                $secret_available = SignManager::getAppSecretByAppKey($app_key, $platform, $app_secret);
            }
        } else {
            $secret_available = true;
            $app_secret = null;
            ini_set('display_errors', 1);
            error_reporting(E_ALL);
        }
        if (!$secret_available){
            Debug::getTrack(
                $this->result, 403, '拒绝访问',
                __DIR__, __FILE__, __LINE__, __METHOD__,
                'sign 校验失败'
            );
        } else {
            $this->result = $this->sign->startVerify($app_secret, $sign);
            if ($sign){
                $this->API($this->sign);
            }
        }
        echo $this->onResult();
    }

    protected abstract function API(Verify $sign);

    private function onResult() {
        if (!Debug::isDebug()){
            $this->result = array_diff_key($this->result, ['debug' => array()]);
        }
        return json_encode($this->result, 320);
    }
}