<?php

namespace sgpublic\scit\tool\core;

class Verify {
    private ?array $params = null;
    private ?array $params_original = null;
    private int $timeout = 600;

    public function insert($param_array){
        if ($param_array != null) {
            foreach ($param_array as $x => $x_value) {
                if (empty($x_value)) {
                    if (isset($_REQUEST[$x])){
                        $this->params_original[$x] = $_REQUEST[$x];
                    } else {
                        $this->params_original[$x] = isset($_COOKIE[$x]) ? $_COOKIE[$x] : null;
                    }
                } else if (isset($_REQUEST[$x])) {
                    $this->params_original[$x] = $_REQUEST[$x];
                }
                $this->params[$x] = isset($_REQUEST[$x]) ? $_REQUEST[$x] : $x_value;
            }
            $this->addDefaultParams('ts');
            $this->addDefaultParams('sign');
            $this->addDefaultParams('app_key');
            $this->addDefaultParams('platform', 'web');
        }
    }

    private function addDefaultParams(string $key, string $default_value = null){
        $this->params_original[$key] = isset($_REQUEST[$key]) ? $_REQUEST[$key] : $default_value;
        $this->params[$key] = isset($_REQUEST[$key]) ? $_REQUEST[$key] : $default_value;
    }

    public function startVerify(string $app_secret = null, bool &$result = null): array {
        $sign = "";
        $this_params = $this->params_original;
        ksort($this_params);
        foreach ($this_params as $x => $x_value){
            if (empty($x_value)){
                if (empty($this_params[$x])) {
                    $result = false;
                    Debug::getTrack(
                        $result_array, 406, '参数缺失',
                        __DIR__, __FILE__, __LINE__, __METHOD__,
                        $x
                    );
                    return $result_array;
                }
            } else if ($x != 'sign'){
                $sign = "$sign&$x=$x_value";
            }
        }
        $sign = substr($sign, 1);
        if (!Debug::isDebug()){
            $this->params['ts'] = isset($_REQUEST['ts']) ? $_REQUEST['ts'] : 0;
            $time_now = time() - $this->params['ts'];
            if ($time_now > $this->timeout or $time_now < -$this->timeout) {
                $result = false;
                Debug::getTrack(
                    $result_array, 412, '拒绝访问',
                    __DIR__, __FILE__, __LINE__, __METHOD__,
                    '时间戳无效'
                );
                return $result_array;
            }

            $this->params['sign'] = isset($_REQUEST['sign']) ? $_REQUEST['sign'] : "";
            if (empty($this->params['sign'])) {
                $result = false;
                Debug::getTrack(
                    $result_array, 401, '服务签名缺失',
                    __DIR__, __FILE__, __LINE__, __METHOD__
                );
                return $result_array;
            }
            $sign_result = md5($sign.$app_secret);
            if ($sign_result != $this->params['sign']) {
                $result = false;
                Debug::getTrack(
                    $result_array, 403, '拒绝访问',
                    __DIR__, __FILE__, __LINE__, __METHOD__,
                    'sign 校验失败'
                );
//                $result_array['sign'] = $sign_result;
                return $result_array;
            }
        }
        $result = true;
        return ['code' => 200, 'message' => 'success.'];
    }

    public function getParameter($index){
        $this_result = array_key_exists($index, $this->params) ? $this->params[$index] : null;
        if ($this_result == null){
            return null;
        }
        if (strval(intval($this_result)) == $this_result){
            return intval($this_result);
        } else {
            return strval($this_result);
        }
    }
}