<?php


namespace sgpublic\scit\tool\helper;

require SCIT_EDU_TOOL_ROOT."/manager/InfoManager.php";

use sgpublic\scit\tool\core\Debug;
use sgpublic\scit\tool\curl\CurlClientBuilder;
use sgpublic\scit\tool\curl\CurlRequestBuilder;
use sgpublic\scit\tool\curl\CurlToolException;
use sgpublic\scit\tool\token\TokenChecker;

class InfoHelper {
    private TokenChecker $checker;

    private function __construct(TokenChecker $checker) {
        $this->checker = $checker;
    }

    public static function getInterface(TokenChecker $checker): InfoHelper {
        if ($checker->check() == 0) {
            return new InfoHelper($checker);
        }
        throw new SessionHelperException('Invalid token or username.');
    }

    public function refresh(array &$info = null): array {
        try {
            $result = SessionHelper::getInterfaceByTokenChecker($this->checker)->get($session);
            if ($result['code'] == 200) {
                $client = CurlClientBuilder::getInterface()
                    ->followLocation(false)
                    ->build();
                $url = "http://218.6.163.93:8081/xsgrxx.aspx?xh={$this->checker->getUid()}";
                $request = CurlRequestBuilder::getInterface()
                    ->url($url)
                    ->addCookie('ASP.NET_SessionId', $session)
                    ->build();
                $response = $client->newCall($request)->execute();
                if (!preg_match('/__VIEWSTATE" value="(.*?)"/', $response->body(), $matches)) {
                    /*
                     * Object move to here
                     * 抱歉，身份验证失败，请联系管理员！
                     */
                    Debug::getTrack(
                        $result, 502, '服务繁忙',
                        __DIR__, __FILE__, __LINE__, __METHOD__,
                        'Object move to here'
                    );
                } else {
//                    $body = iconv("GBK", "UTF-8", $response->body());
                    $body = $response->body();
                    preg_match('/"lbl_dqszj">(.*?)</', $body, $matches);
                    $grade = $matches[1];

                    preg_match('/"xm">(.*?)</', $body, $matches);
                    $name = $matches[1];

                    preg_match('/"lbl_xzb">(.*?)</', $body, $matches);
                    $lbl_xzb = $matches[1];
                    preg_match('/(\d+)\.?(\d+)班/', $lbl_xzb, $matches);
                    $class = $matches[1].$matches[2];

                    preg_match('/"lbl_xy">(.*?)</', $body, $matches);
                    $lbl_xy = $matches[1];

                    preg_match('/"lbl_zymc">(.*?)</', $body, $matches);
                    $lbl_zymc = str_replace('(', '\(', $matches[1]);

                    $url = "http://218.6.163.93:8081/tjkbcx.aspx?xh={$this->checker->getUid()}";
                    $request = CurlRequestBuilder::getInterface()
                        ->url($url)
                        ->addCookie('ASP.NET_SessionId', $session)
                        ->build();
                    $response = $client->newCall($request)->execute();

//                    $body = iconv("GBK", "UTF-8", $response->body());
                    $body = $response->body();
                    if (preg_match('/完成评价工作后/', $body)){
                        if (ChartManager::getCharsetIDWithClassName($lbl_xzb, $lbl_xy_id, $lbl_zymc_id) == false){
                            Debug::getTrack(
                                $result, 500, '请先完成教评',
                                __DIR__, __FILE__, __LINE__, __METHOD__
                            );
                            return $result;
                        }
                    } else {
                        preg_match("/value=\"(.*?)\">$matches[1]/", $body, $matches);
                        $lbl_zymc_id = $matches[1];

                        preg_match("/value=\"(.*?)\">$lbl_xy/", $body, $matches);
                        $lbl_xy_id = $matches[1];
                    }

                    if ($name == null | $lbl_xy == null | $lbl_xy_id == null | $lbl_zymc == null | $lbl_zymc_id == null
                        | $lbl_xzb == null | $lbl_xzb == null | $class == null | $grade == null) {
                        Debug::getTrack(
                            $result, 404, '部分信息获取失败',
                            __DIR__, __FILE__, __LINE__, __METHOD__
                        );
                    } else {
                        ChartManager::writeChart(
                            intval($lbl_xy_id), $lbl_xy,
                            intval($lbl_zymc_id), $lbl_zymc,
                            intval($class), $lbl_xzb
                        );
                        InfoManager::getInterface($this->checker)->update(
                            $name, intval($lbl_xy_id), intval($lbl_zymc_id), intval($class), intval($grade)
                        );
                        $info = [
                            'name' => $name,
                            'faculty' => [
                                'name' => $lbl_xy
                            ],
                            'specialty' => [
                                'name' => $lbl_zymc
                            ],
                            'class' => [
                                'name' => $lbl_xzb
                            ],
                            'grade' => intval($grade)
                        ];
                        $result = ['code' => 200, 'message' => 'success.'];
                    }
                }
            }
        } catch (CurlToolException $e){
            Debug::getTrack(
                $result, 502, '无法连接教务系统，可能由于教务系统正在维护或处于高峰期',
                __DIR__, __FILE__, __LINE__, __METHOD__,
                $e->getMessage()
            );
        }
        return $result;
    }

    public function get(array &$info = null): array {
        if (InfoManager::getInterface($this->checker)->get($info)){
            $result = ['code' => 200, 'message' => 'success.'];
        } else {
            $result = $this->refresh($info);
        }
        return $result;
    }
}