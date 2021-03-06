<?php


namespace sgpublic\scit\tool\helper;

use sgpublic\scit\tool\core\Debug;
use sgpublic\scit\tool\curl\CurlClientBuilder;
use sgpublic\scit\tool\curl\CurlRequestBuilder;
use sgpublic\scit\tool\curl\CurlToolException;
use sgpublic\scit\tool\curl\FormBody;
use sgpublic\scit\tool\curl\FormBodyBuilder;
use sgpublic\scit\tool\token\TokenChecker;

class EvaluateHelper {
    private TokenChecker $checker;
    public bool $open = true;

    private int $count = 0;
    private array $options = array();

    private string $session;
    private string $view_state;

    private function __construct(TokenChecker $checker) {
        $this->checker = $checker;
    }

    public static function getInterface(TokenChecker $checker): EvaluateHelper {
        if ($checker->check() == 0) {
            return new EvaluateHelper($checker);
        }
        throw new SessionHelperException('Invalid token or username.');
    }

    public function getTotalCount(): array {
        return $this->getID();
    }

    public function get(int $index): array {
        $info = null;
        $result = $this->getID($index, $id);
        if ($result['code'] == 200){
            if ($result['count'] == 0){
                Debug::getTrack(
                    $result, 502, '教评未开放或您已完成教评',
                    __DIR__, __FILE__, __LINE__, __METHOD__
                );
                return $result;
            } else {
                $client = CurlClientBuilder::getInterface()
                    ->followLocation(false)
                    ->build();
                $request = CurlRequestBuilder::getInterface()
                    ->url("http://218.6.163.93:8081/xsjxpj.aspx?xh={$this->checker->getUid()}&xkkh=$id")
                    ->addCookie('ASP.NET_SessionId', $this->session)
                    ->build();
                $response = $client->newCall($request)->execute();
                $body = iconv("GBK", "UTF-8", $response->body());
                $result_evaluate = $this->doParse($body, str_replace(
                    '(', '\(', str_replace(')', '\)', $id)
                ), $info);
                if ($info == null){
                    $result['evaluate'] = $result_evaluate;
                    return $result;
                }
            }
        }
        Debug::getTrack(
            $result, 502, '请求处理出错',
            __DIR__, __FILE__, __LINE__, __METHOD__,
            $info
        );
        return $result;
    }

    private function doParse(string $body, string $id, string &$info = null): array {
        if (preg_match('/__VIEWSTATE" value="(.*?)"/', $body, $matches) === false) {
            $info = '课程 ID 获取失败';
            return array();
        }
        $evaluations = [];

        if (preg_match('/value="'.$id.'">(.*?)</', $body, $matches) === false) {
            $info = '课程名称获取失败';
            return array();
        }
        $evaluations['subject'] = $matches[1];

        $teachers = [];
        if (preg_match('/valign="Middle">([\s\S]*?)tr/', $body, $matches)) {
            $teachers_string = explode('</td>', str_replace('<td>','', $matches[1]));
            foreach ($teachers_string as $item) {
                if (strpos('<', $item) == false){
                    array_push($teachers, $item);
                }
            }
            array_pop($teachers);
            if (sizeof($teachers) == 0){
                if (preg_match_all('/<table border=\'0\' cellpadding=\'0\' cellspacing=\'0\'>([\s\S]*?)<\/table>/',
                    $body, $matches)){
                    foreach ($matches[1] as $item) {
                        if (preg_match('/\)">([^A-Za-z]*?)<\/a>/', $item, $matches)){
                            array_push($teachers, $matches[1]);
                        }
                    }
                }
            }
        }
        if (sizeof($teachers) == 0){
            $info = '教师姓名获取失败';
            return array();
        }

        if (preg_match('/<img src="(.*?)" width="40px" height="60px">/', $body, $matches)) {
            $evaluations['avatar'] = 'http://218.6.163.93:8081/' . $matches[1];
        }

        if (preg_match_all('/class="FFF">(.*?)</', $body, $matches) === false) {
            $info = '问题列表获取失败';
            return array();
        }
        $questions = [];
        foreach ($matches[1] as $item) {
            $question_index = [];
            $item = str_replace(" ", "", $item);
            $question_info = explode("？", $item);
            if (sizeof($question_info) == 1) {
                $question_info = explode("?", $item);
            }
            $question_index['text'] = $question_info[0] . "？";

            $question_options_info = explode(';', $question_info[1]);
            $question_options = [];
            foreach ($question_options_info as $option_item) {
                array_unshift(
                    $question_options,
                    substr(str_replace('.', '', $option_item), 1)
                );
            }
            $question_index['options'] = $question_options;

            array_push($questions, $question_index);
        }
        $evaluations['questions'] = $questions;

        $evaluations_data = [];
        $options = [];
        $success = true;
        for ($t_i = 1; $t_i < sizeof($teachers) + 1; $t_i++){
            $evaluations_data_t = [];
            $evaluations_data_t['teacher'] = $teachers[$t_i - 1];

            $evaluations_data_o = [];
            $options_index = [];
            for ($o_i = 2; $o_i < sizeof($questions) + 2; $o_i++){
                //DataGrid1__ctl2_JS1
                $option_selected = sizeof($evaluations_data_o);

                if (preg_match("/DataGrid1__ctl{$o_i}_JS{$t_i}\">([\s\S]*?)select>/", $body, $matches)){
                    if (preg_match_all('/<option(.*?)option>/', $matches[1], $matches_select)){
                        if (sizeof($matches_select[1]) - 1 == sizeof($evaluations['questions'][$o_i - 2]['options'])){
                            $selections = $matches_select[1];
                            $options_index_question = [];
                            for ($s_i = 0; $s_i < sizeof($selections); $s_i++){
                                if (preg_match('/>(.*?)</', $selections[$s_i], $matches_selections)){
                                    if ($s_i != 0){
                                        array_push($options_index_question, mb_convert_encoding(
                                            $matches_selections[1], 'GBK', 'UTF-8')
                                        );
                                    }
                                }
                                if (preg_match('/selected/', $selections[$s_i])){
                                    array_push($evaluations_data_o, 6 - $s_i);
                                }
                            }
                            if (sizeof($options_index_question) != sizeof($selections) - 1){
                                $success = false;
                            } else {
                                array_push($options_index, $options_index_question);
                            }
                        }
                    }
                }
                if (sizeof($evaluations_data_o) == $option_selected){
                    array_push($evaluations_data_o, 0);
                }
            }
            $evaluations_data_t['options'] = $evaluations_data_o;
            array_push($evaluations_data, $evaluations_data_t);
            array_push($options, $options_index);
        }
        if ($success){
            $this->options = $options;
            $evaluations['evaluations'] = $evaluations_data;
            return $evaluations;
        } else {
            $info = '选项值获取失败';
            return array();
        }
    }

    public function post(int $index, string $data): array {
        $result = $this->getID($index, $id);
        if ($result['code'] == 200){
            if ($result['count'] == 0){
                Debug::getTrack(
                    $result, 502, '您已完成教评',
                    __DIR__, __FILE__, __LINE__, __METHOD__
                );
            } else {
                $client = CurlClientBuilder::getInterface()
                    ->followLocation(false)
                    ->build();
                $request = CurlRequestBuilder::getInterface()
                    ->url("http://218.6.163.93:8081/xsjxpj.aspx?xh={$this->checker->getUid()}&xkkh=$id")
                    ->addCookie('ASP.NET_SessionId', $this->session);
                $response = $client->newCall($request->build())->execute();
                $body = iconv("GBK", "UTF-8", $response->body());

                if (preg_match('/__VIEWSTATE" value="(.*?)"/', $body, $matches)) {
                    $this->view_state = $matches[1];
                    $this->doParse($body, str_replace(
                        '(', '\(', str_replace(')', '\)', $id)
                    ), $info);
                    if ($info == null){
                        if ($this->doSubmit(false, $id, $data, $form, $info)){
                            $request->post($form);
                            $response = $client->newCall($request->build())->execute();
                            $body = iconv("GBK", "UTF-8", $response->body());
                            if (preg_match('/__VIEWSTATE" value="(.*?)"/', $body)) {
                                if (preg_match('/<script>alert\(\'(.*?)\'\);<\/script>/', $body, $matches)){
                                    if ($matches[1] == '所有评价已完成，现在可以提交！'){
                                        $this->doSubmit(true, $id, $data, $form);
                                        $request->post($form);
                                        $response = $client->newCall($request->build())->execute();
                                        $body = iconv("GBK", "UTF-8", $response->body());
                                        if (preg_match('/<script>alert\(\'(.*?)\'\)<\/script>/', $body, $matches)){
                                            return ['code' => 200, 'message' => 'success.'];
                                        }
                                        Debug::getTrack(
                                            $result, 502, '提交失败',
                                            __DIR__, __FILE__, __LINE__, __METHOD__
                                        );
                                        return ['code' => 200, 'message' => 'success.'];
                                    } else if ($matches[1] == '您已完成评价！'){
                                        return ['code' => 200, 'message' => 'success.'];
                                    } else if ($matches[1] == '当前<评教师>中显示的教师，请全部评完！'){
                                        Debug::getTrack(
                                            $result, 502, '请提交正确格式的数据',
                                            __DIR__, __FILE__, __LINE__, __METHOD__
                                        );
                                    }
                                } else {
                                    return ['code' => 200, 'message' => 'success.'];
                                }
                            } else {
                                Debug::getTrack(
                                    $result, 502, '请求处理出错',
                                    __DIR__, __FILE__, __LINE__, __METHOD__,
                                    '未发现 __VIEWSTATE'
                                );
                            }
                        } else {
                            Debug::getTrack(
                                $result, 502, $info,
                                __DIR__, __FILE__, __LINE__, __METHOD__
                            );
                        }
                    } else {
                        Debug::getTrack(
                            $result, 502, '请求处理出错',
                            __DIR__, __FILE__, __LINE__, __METHOD__,
                            '未发现 __VIEWSTATE'
                        );
                    }
                } else {
                    Debug::getTrack(
                        $result, 502, '请求处理出错',
                        __DIR__, __FILE__, __LINE__, __METHOD__,
                        '未发现 __VIEWSTATE'
                    );
                }
            }
        }
        return $result;
    }

    private function doSubmit(bool $is_post, string $id, string $evaluations, FormBody &$form = null, string &$info = null): string {
        $form_builder = FormBodyBuilder::getInterface()
            ->add('__EVENTTARGET')
            ->add('__EVENTARGUMENT')
            ->add('__VIEWSTATE', $this->view_state)
            ->add('pjkc', $id);

        $evaluations = json_decode($evaluations, 320);
        if (isset($evaluations['o']) == false){
            $info = "提交选项为空";
            return false;
        }
        $options_array = $evaluations['o'];
        for ($t_i = 0; $t_i < sizeof($options_array); $t_i++){
            $options = $options_array[$t_i];
            $same_check_number = 0;
            for ($o_i = 0; $o_i < sizeof($options); $o_i++){
                $same_check_number += $options_array[$t_i][$o_i];
                if ($options_array[$t_i][$o_i] == 0){
                    $info = '请做出选择后再提交数据';
                    return false;
                }
                $o_index = $o_i + 2;
                $t_index = $t_i + 1;
                $form_builder->add("DataGrid1:_ctl$o_index:JS$t_index", $this->options[$t_i][$o_i][
                    5 - $options_array[$t_i][$o_i]
                ]);
                $form_builder->add("DataGrid1:_ctl$o_index:txtjs$t_index");
            }
            if ($same_check_number % sizeof($this->options[$t_i]) == 0) {
                $info = '请勿全部提交相同选项';
                return false;
            }
        }
        if (isset($evaluations['p']) == false){
            $info = "提交评语为空";
            return false;
        }
        $form_builder->add('pjxx', mb_convert_encoding(
            $evaluations['p'], 'GBK', 'UTF-8'
        ));
        $form_builder->add('txt1');
        $form_builder->add('TextBox1', 0);
        if ($is_post){
            $form_builder->add('Button2', mb_convert_encoding(
                ' 提  交 ', 'GBK', 'UTF-8'
            ));
        } else {
            $form_builder->add('Button1', mb_convert_encoding(
                '保  存', 'GBK', 'UTF-8'
            ));
        }
        $form = $form_builder->build();
        return true;
    }

    private function getID(int $index = 0, string &$id = null): array {
        try {
            $result = SessionHelper::getInterfaceByTokenChecker($this->checker)->get($session);
            if ($result['code'] == 200) {
                $this->session = $session;
                if ($this->open){
                    $client = CurlClientBuilder::getInterface()
                        ->followLocation(false)
                        ->build();
                    $request = CurlRequestBuilder::getInterface()
                        ->url('http://218.6.163.93:8081/xs_main.aspx?xh='.$this->checker->getUid())
                        ->addCookie('ASP.NET_SessionId', $session)
                        ->build();
                    $response = $client->newCall($request)->execute();

                    if (preg_match('/__VIEWSTATE" value="(.*?)"/', $response->body(), $matches) !== false) {
                        $this->view_state = $matches[1];

                        $body = iconv("GBK","UTF-8", $response->body());
                        if (preg_match('/教学质量评价([\s\S]*?)ul>/', $body, $matches) !== false){
                            $list = $matches[1];
                            if (preg_match_all('/href="xsjxpj(.*?)"/', $list, $matches)) {
                                $this->count = sizeof($matches[1]);
                                if ($index < 0 or $index > $this->count) {
                                    Debug::getTrack(
                                        $result, 502, 'index超出范围',
                                        __DIR__, __FILE__, __LINE__, __METHOD__
                                    );
                                    return $result;
                                } else {
                                    $id = explode('=', explode('&',
                                        explode("?", $matches[1][$index])[1]
                                    )[0])[1];
                                }
                            }
                            return ['code' => 200, 'message' => 'success.', 'count' => $this->count];
                        } else {
                            $info = '获取教评列表失败';
                        }
                    } else {
                        $info = '未发现 __VIEWSTATE';
                    }
                } else {
                    return ['code' => 200, 'message' => 'success.', 'count' => $this->count];
                }
            } else {
                $info = $result['message'];
            }
        } catch (CurlToolException $e) {
            $info = $e->getMessage();
        }
        Debug::getTrack(
            $result, 502, '请求处理错误',
            __DIR__, __FILE__, __LINE__, __METHOD__,
            $info
        );
        return $result;
    }
}