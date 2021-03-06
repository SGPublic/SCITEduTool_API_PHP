<?php


namespace sgpublic\scit\tool\helper;

use sgpublic\scit\tool\core\Debug;
use sgpublic\scit\tool\curl\CurlClientBuilder;
use sgpublic\scit\tool\curl\CurlRequestBuilder;
use sgpublic\scit\tool\curl\CurlToolException;
use sgpublic\scit\tool\token\TokenChecker;

class ExamHelper {
    private TokenChecker $checker;

    private function __construct(TokenChecker $checker) {
        $this->checker = $checker;
    }

    public static function getInterface(TokenChecker $checker): ExamHelper {
        if ($checker->check() == 0) {
            return new ExamHelper($checker);
        }
        throw new SessionHelperException('Invalid token or username.');
    }

    public function get(): array {
        try {
            $result = SessionHelper::getInterfaceByTokenChecker($this->checker)->get($session);
            if ($result['code'] == 200){
                $client = CurlClientBuilder::getInterface()
                    ->followLocation(false)
                    ->build();
                $request = CurlRequestBuilder::getInterface()
                    ->url('http://218.6.163.93:8081/xskscx.aspx?xh='.$this->checker->getUid())
                    ->addCookie('ASP.NET_SessionId', $session)
                    ->build();
                $response = $client->newCall($request)->execute();
//                $body = iconv("GBK","UTF-8", $response->body());
                $body = $response->body();
                $body = str_replace("\n", '', $response->body());
                $body = str_replace(" class=\"alt\"", '', $body);

                if (preg_match('/__VIEWSTATE" value="(.*?)"/', $body, $matches)){
                    $exam_return = array();
                    $exam_return['code'] = 200;
                    $exam_return['message'] = 'success.';

                    $exam_result_re = [
                        'count' => 0,
                        'data' => []
                    ];
                    if (preg_match('/id="DataGrid1"(.*?)<\/table>/', $body, $exam_matched)
                        and preg_match_all('/<tr>(.*?)<\/tr>/', $exam_matched[1], $exam_matched)){
                        $exam_index = 0;
                        foreach ($exam_matched[1] as $exam_item){
                            if (preg_match_all('/<td>(.*?)<\/td>/', $exam_item, $explode_exam_index)){
                                $explode_exam_index = $explode_exam_index[1];
//
                                $exam_result_index_re['name'] = $explode_exam_index[1] == '&nbsp;' ? '' : $explode_exam_index[1];
                                $exam_result_index_re['time'] = $explode_exam_index[3] == '&nbsp;' ? '' : $explode_exam_index[3];
                                $exam_result_index_re['location'] = $explode_exam_index[4] == '&nbsp;' ? '' : $explode_exam_index[4];
                                $exam_result_index_re['sit_num'] = $explode_exam_index[6] == '&nbsp;' ? '' : $explode_exam_index[6];

                                $exam_result_re['data'][$exam_index] = $exam_result_index_re;
                                $exam_index = $exam_index + 1;
                            }
                        }
                        $exam_result_re['count'] = $exam_index;
                    }
                    $exam_return['exam'] = $exam_result_re;

                    $result = $exam_return;
                } else {
                    Debug::getTrack(
                        $result, 502, '请求处理出错',
                        __DIR__, __FILE__, __LINE__, __METHOD__
                    );
                }
            }
        } catch (CurlToolException $e) {
            Debug::getTrack(
                $result, 502, '无法连接教务系统，可能由于教务系统正在维护或处于高峰期',
                __DIR__, __FILE__, __LINE__, __METHOD__,
                $e->getMessage()
            );
        }
        return $result;
    }
}