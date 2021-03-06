<?php


namespace sgpublic\scit\tool\helper;

require SCIT_EDU_TOOL_ROOT."/manager/TableManager.php";

use sgpublic\scit\tool\core\Debug;
use sgpublic\scit\tool\curl\CurlClientBuilder;
use sgpublic\scit\tool\curl\CurlRequestBuilder;
use sgpublic\scit\tool\curl\CurlToolException;
use sgpublic\scit\tool\curl\FormBodyBuilder;
use sgpublic\scit\tool\token\TokenChecker;

class TableHelper {
    private TokenChecker $checker;
    private array $result;
    private ?array $info;

    private function __construct(TokenChecker $checker) {
        $this->checker = $checker;
        $this->result = InfoHelper::getInterface($checker)->get($info);
        $this->info = $info;
    }

    public static function getInterface(TokenChecker $checker): TableHelper {
        if ($checker->check() == 0) {
            return new TableHelper($checker);
        }
        throw new SessionHelperException('Invalid token or username.');
    }

    public function refresh(string $year, int $semester, array &$table = null): array {
        try {
            $result = SessionHelper::getInterfaceByTokenChecker($this->checker)->get($session);
            if ($result['code'] != 200){
                return $result;
            }

            $table_id = self::getTableID($this->info, $year, $semester);

            $url = "http://218.6.163.93:8081/tjkbcx.aspx?xh={$this->checker->getUid()}";
            //第1次访问
            $client = CurlClientBuilder::getInterface()
                ->followLocation(false)
                ->build();
            $request = CurlRequestBuilder::getInterface()
                ->url($url)
                ->addCookie('ASP.NET_SessionId', $session)
                ->build();
            $response = $client->newCall($request)->execute();
//                $body = iconv("GBK","UTF-8", $response->body());
            $body = $response->body();

            if(preg_match('/__VIEWSTATE" value="(.*?)"/', $body, $matches)){
                $viewstate = $matches[1];
            } else {
                Debug::getTrack(
                    $result, 502, '请求处理错误',
                    __DIR__, __FILE__, __LINE__, __METHOD__,
                    '未发现 __VIEWSTATE'
                );
                return $result;
            }

            if ($this->result['code'] != 200){
                return $this->result;
            }

            //第2次访问
            $form = FormBodyBuilder::getInterface()
                ->add('__EVENTTARGET', 'zy')
                ->add('__EVENTARGUMENT')
                ->add('__LASTFOCUS')
                ->add('__VIEWSTATE', $viewstate)
                ->add('__VIEWSTATEGENERATOR', "3189F21D")
                ->add('xn', $year)
                ->add('xq', $semester)
                ->add('nj', $this->info['grade'])
                ->add('xy', $this->info['faculty']['id'])
                ->add('zy', $this->info['specialty']['id'])
                ->add('kb')
                ->build();
            $request = CurlRequestBuilder::getInterface()
                ->url($url)
                ->addCookie('ASP.NET_SessionId', $session)
                ->post($form)
                ->build();

            $response = $client->newCall($request)->execute();
//            $body = iconv("GBK","UTF-8", $response->body());
            $body = $response->body();

            if (strpos($body, "selected=\"selected\" value=\"".$table_id."\"") == false) {
                if (preg_match('/__VIEWSTATE" value="(.*?)"/', $body, $matches)) {
                    $viewstate = $matches[1];
                } else {
                    Debug::getTrack(
                        $result, 502, '请求处理错误',
                        __DIR__, __FILE__, __LINE__, __METHOD__,
                        '未发现 __VIEWSTATE'
                    );
                    return $result;
                }
//                print_r([
//                    'view_state' => $viewstate,
//                    'html' => $body
//                ]);

                //第3次访问
                $form = FormBodyBuilder::getInterface()
                    ->add('__EVENTTARGET', 'kb')
                    ->add('__EVENTARGUMENT')
                    ->add('__LASTFOCUS')
                    ->add('__VIEWSTATE', $viewstate)
                    ->add('__VIEWSTATEGENERATOR', "3189F21D")
                    ->add('xn', $year)
                    ->add('xq', $semester)
                    ->add('nj', $this->info['grade'])
                    ->add('xy', $this->info['faculty']['id'])
                    ->add('zy', $this->info['specialty']['id'])
                    ->add('kb', $table_id)
                    ->build();
                $request = CurlRequestBuilder::getInterface()
                    ->url($url)
                    ->addCookie('ASP.NET_SessionId', $session)
                    ->post($form)
                    ->build();
                $response = $client->newCall($request)->execute();
            }

//            $table_html = iconv("GBK","UTF-8", $response->body());
            $table_html = $response->body();
            if (!preg_match('/__VIEWSTATE" value="(.*?)"/', $table_html)){
                Debug::getTrack(
                    $result, 502, '请求处理错误',
                    __DIR__, __FILE__, __LINE__, __METHOD__,
                    '未发现 __VIEWSTATE'
                );
                return $result;
            }

            if (strpos($table_html, "selected=\"selected\" value=\"".$table_id."\"") == false) {
                Debug::getTrack(
                    $result, 502, '数据为空',
                    __DIR__, __FILE__, __LINE__, __METHOD__,
                    '无法选中目标课表数据'
                );
                return $result;
            }

            //开始解析
            $table_html = str_replace("（", "(", $table_html);
            $table_html = str_replace("）", ")", $table_html);

            $matches_class = explode('<tr>', $table_html);

            $table_result = array();
            $result_count = 0;
            for ($class = 1; $class <= 5; $class++){
                $class_index = 2 * $class + 1;

                $matches_day = explode('</td>', $matches_class[$class_index]);

                $day_fault = [0, 1, 0, 1, 0];
                for ($day = 2; $day <= 7; $day++){
                    $day_class_string = $matches_day[$day - $day_fault[$class - 1]];

                    $day_class_count = array();
                    if ($day_class_string == '<td align="Center">&nbsp;' || $day_class_string == '<td align="Center" width="7%">&nbsp;'){
                        $table_result[$day - 2][$class - 1] = ['count' => 0, 'data' => ''];
                    } else {
                        $day_class_data = explode('<br>', $day_class_string);
                        $day_class_data_count = intval((count($day_class_data) + 1) / 7);

                        $day_class = array();

                        for ($count = 0; $count <= $day_class_data_count - 1; $count++){
                            if ($count == 0) {
                                $day_class_count['name'] = explode('>', $day_class_data[$count * 7])[1];
                            } else {
                                $day_class_count['name'] = $day_class_data[$count * 7];
                            }
                            $string_class = substr($day_class_data[1 + $count * 7], 0,
                                strpos($day_class_data[1 + $count * 7], '('));
                            if (strpos($string_class, ',')){
                                $range_array = explode(',', $string_class);
                            } else {
                                $range_array = [$day_class_data[1 + $count * 7]];
                            }
                            $day_class_count['range'] = [];
                            $week_range_0 = stripos($day_class_data[1 + $count * 7], '双');
                            $week_range_1 = stripos($day_class_data[1 + $count * 7], '单');
                            foreach ($range_array as $item) {
                                if (strpos($item, '-')) {
                                    $local_range = explode('-', $item);
                                } else {
                                    $local_range = [$item, $item];
                                }
                                for ($index = intval($local_range[0]); $index <= intval($local_range[1]); $index++) {
                                    if (!$week_range_0 and intval($index / 2) * 2 != $index) {
                                        $day_class_count['range'][] = $index;
                                    } else if (!$week_range_1 and intval($index / 2) * 2 == $index) {
                                        $day_class_count['range'][] = $index;
                                    }
                                }
                            }
                            $day_class_count['teacher'] = str_replace("\n", '', $day_class_data[2 + $count * 7]);

                            $day_class_count['room'] = $day_class_data[3 + $count * 7];

                            $day_class[$count] = $day_class_count;
                        }

                        $table_result[$day - 2][$class - 1] = [
                            'count' => $day_class_data_count,
                            'data' => $day_class
                        ];
                        $result_count++;
                    }
                }
            }

            if ($result_count == 0){
                Debug::getTrack(
                    $result, 404, '数据为空',
                    __DIR__, __FILE__, __LINE__, __METHOD__,
                    '未发现 __VIEWSTATE'
                );
                return $result;
            } else {
                $table = $table_result;
                $manager = TableManager::getInterface($this->checker, $this->info, $year, $semester);
                if ($manager->checkTableExit()){
                    $manager->update($table);
                } else {
                    $manager->insert($table);
                }
                return ['code' => 200, 'message' => 'success.'];
            }
        } catch (CurlToolException $e){
            Debug::getTrack(
                $result, 502, '无法连接教务系统，可能由于教务系统正在维护或处于高峰期',
                __DIR__, __FILE__, __LINE__, __METHOD__,
                $e->getMessage()
            );
            return $result;
        }
    }

    public function get(string $year, int $semester, array &$table = null): array {
        if ($this->result['code'] == 200){
            $result = TableManager::getInterface($this->checker, $this->info, $year, $semester)
                ->get($table);
            if ($result){
                return ['code' => 200, 'message' => 'success.'];
            } else {
                return $this->refresh($year, $semester, $table);
            }
        } else {
            return $this->result;
        }
    }

    public static function getTableID(array $t_info, string $year, int $semester): string {
        $class_id = '0'.$t_info['class']['id'];
        return $t_info['grade'].$t_info['specialty']['id'].$year.$semester
            .substr($t_info['grade'],2,2).$t_info['specialty']['id']
            .substr($class_id, strlen($class_id) - 2, 2);
    }
}