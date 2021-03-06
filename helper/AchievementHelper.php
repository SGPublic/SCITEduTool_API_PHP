<?php


namespace sgpublic\scit\tool\helper;

use sgpublic\scit\tool\core\Debug;
use sgpublic\scit\tool\curl\CurlClientBuilder;
use sgpublic\scit\tool\curl\CurlRequestBuilder;
use sgpublic\scit\tool\curl\CurlToolException;
use sgpublic\scit\tool\curl\FormBodyBuilder;
use sgpublic\scit\tool\token\TokenChecker;

class AchievementHelper {
    private TokenChecker $checker;

    private function __construct(TokenChecker $checker) {
        $this->checker = $checker;
    }

    public static function getInterface(TokenChecker $checker): AchievementHelper {
        if ($checker->check() == 0) {
            return new AchievementHelper($checker);
        }
        throw new SessionHelperException('Invalid token or username.');
    }

    public function get(string $year, int $semester, array &$achievement = null): array {
        try {
            $result = SessionHelper::getInterfaceByTokenChecker($this->checker)->get($session);
            if ($result['code'] == 200) {
                //第一次访问
                $client = CurlClientBuilder::getInterface()
                    ->followLocation(false)
                    ->build();
                $request = CurlRequestBuilder::getInterface()
                    ->url('http://218.6.163.93:8081/xscj.aspx?xh='.$this->checker->getUid())
                    ->addCookie('ASP.NET_SessionId', $session)
                    ->build();
                $response = $client->newCall($request)->execute();

                if (preg_match('/__VIEWSTATE" value="(.*?)"/', $response->body(), $matches)) {
                    $viewstate = $matches[1];
                } else {
                    Debug::getTrack(
                        $result, 502, '请求处理错误',
                        __DIR__, __FILE__, __LINE__, __METHOD__,
                        '未发现 __VIEWSTATE'
                    );
                    return $result;
                }

                //第二次访问
                $form = FormBodyBuilder::getInterface()
                    ->add('__VIEWSTATE', $viewstate)
                    ->add('__VIEWSTATEGENERATOR', "17EB693E")
                    ->add('ddlXN', $year)
                    ->add('ddlXQ', $semester)
                    ->add('txtQSCJ', 0)
                    ->add('txtZZCJ', 100)
                    ->add('Button1', '按学期查询')
                    ->build();
                $request = CurlRequestBuilder::getInterface()
                    ->url('http://218.6.163.93:8081/xscj.aspx?xh=' . $this->checker->getUid())
                    ->addCookie('ASP.NET_SessionId', $session)
                    ->post($form)
                    ->build();

                $response = $client->newCall($request)->execute();
//                $body = iconv("GBK","UTF-8", $response->body());
                $body = str_replace("\n", '', $response->body());
                $body = str_replace(" class=\"alt\"", '', $body);

                if (preg_match('/__VIEWSTATE" value="(.*?)"/', $body, $matches)) {
                    $grade_return = array();

                    $grade_return['passed'] = [
                        'count' => 0,
                        'data' => array(),
                    ];
                    if (preg_match('/id="DataGrid1"(.*?)<\/table>/', $body, $passed_matched)
                        and preg_match_all('/<tr>(.*?)<\/tr>/', $passed_matched[1], $passed_matched)){
                        $grade_index = 0;
                        foreach ($passed_matched[1] as $passed_item){
                            if (preg_match_all('/<td>(.*?)<\/td>/', $passed_item, $explode_grade_index)){
                                $explode_grade_index = $explode_grade_index[1];
                                $grade_result_index_begin['name'] = $explode_grade_index[1] == '&nbsp;' ? '' : $explode_grade_index[1];
                                $grade_result_index_begin['paper_score'] = $explode_grade_index[3] == '&nbsp;' ? '' : $explode_grade_index[3];
                                $grade_result_index_begin['mark'] = $explode_grade_index[4] == '&nbsp;' ? '' : $explode_grade_index[4];
                                $grade_result_index_begin['retake'] = $explode_grade_index[6] == '&nbsp;' ? '' : $explode_grade_index[6];
                                $grade_result_index_begin['rebuild'] = $explode_grade_index[7] == '&nbsp;' ? '' : $explode_grade_index[7];
                                $grade_result_index_begin['credit'] = $explode_grade_index[8] == '&nbsp;' ? '' : $explode_grade_index[8];
                                $grade_return['passed']['data'][$grade_index] = $grade_result_index_begin;
                                $grade_index = $grade_index + 1;
                            }
                        }
                        $grade_return['passed']['count'] = $grade_index;
                    }

                    $grade_return['failed'] = [
                        'count' => 0,
                        'data' => array(),
                    ];
                    if (preg_match('/id="Datagrid3"(.*?)<\/table>/', $body, $failed_matched)
                        and preg_match_all('/<tr>(.*?)<\/tr>/', $failed_matched[1], $failed_matched)){
                        $grade_index = 0;
                        foreach ($failed_matched[1] as $failed_item){
                            if (preg_match_all('/<td>(.*?)<\/td>/', $failed_item, $explode_grade_index)){
                                $explode_grade_index = $explode_grade_index[1];
                                $grade_result_index_re['name'] = $explode_grade_index[1] == '&nbsp;' ? '' : $explode_grade_index[1];
                                $grade_result_index_re['mark'] = $explode_grade_index[3] == '&nbsp;' ? '' : explode('</', $explode_grade_index[3])[0];

                                $grade_return['failed']['data'][$grade_index] = $grade_result_index_re;
                                $grade_index = $grade_index + 1;
                            }
                        }
                        $grade_return['failed']['count'] = $grade_index;
                    }

                    $achievement = $grade_return;
                    $result = ['code' => 200, 'message' => 'success.'];
                } else {
                    Debug::getTrack(
                        $result, 502, '请求处理错误',
                        __DIR__, __FILE__, __LINE__, __METHOD__,
                        '未发现 __VIEWSTATE'
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