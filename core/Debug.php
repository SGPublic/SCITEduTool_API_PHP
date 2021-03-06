<?php


namespace sgpublic\scit\tool\core;


class Debug {
    public static function isDebug(): bool {
        $app_key = isset($_REQUEST['app_key']) ? $_REQUEST['app_key'] : 'null';
        return $app_key == '2b1be240b7a5dd6c' and strpos(__DIR__, 'debug');
    }

    public static function getTrack(array &$debug = null, int $code,
        string $message, string $dir, string $file, int $line,
        string $method, string $info = null): array {
        $result = array();
        $result['code'] = $code;
        $result['message'] = $message;
        $result['debug'] = [['trace' => str_replace($dir.'\\', '',  $file).'('.$line.'): '.$method]];
        if ($info != null){
            $result['debug'][0]['info'] = $info;
        }

        if (isset($debug['debug']) and is_array($debug['debug'])){
            $pre_debug = $debug['debug'];
            if (sizeof($pre_debug) > 0){
                foreach ($pre_debug as $item){
                    array_push($result['debug'], $item);
                }
            }
        }
        $debug = $result;
        return $result;
    }
}