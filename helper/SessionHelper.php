<?php

namespace sgpublic\scit\tool\helper;

require SCIT_EDU_TOOL_ROOT.'/manager/SessionManager.php';

use Exception;
use sgpublic\scit\tool\core\Debug;
use sgpublic\scit\tool\curl\CurlClientBuilder;
use sgpublic\scit\tool\curl\CurlRequestBuilder;
use sgpublic\scit\tool\curl\CurlToolException;
use sgpublic\scit\tool\curl\FormBodyBuilder;
use sgpublic\scit\tool\rsa\RSAStaticUnit;
use sgpublic\scit\tool\sql\SQLColumnsBuilder;
use sgpublic\scit\tool\sql\SQLException;
use sgpublic\scit\tool\sql\SQLReadCallback;
use sgpublic\scit\tool\sql\SQLTableBuilder;
use sgpublic\scit\tool\sql\SQLWhereOperatorCreator;
use sgpublic\scit\tool\token\TokenChecker;

class SessionHelper extends SQLReadCallback {
    private string $username;
    private string $password;

    private function __construct(string $username, string $password = ''){
        $this->username = $username;
        $this->password = $password;
    }

    public static function getInterface(string $username, string $password = ''): SessionHelper {
        return new SessionHelper($username, $password);
    }

    public static function getInterfaceByTokenChecker(TokenChecker $checker){
        if ($checker->check() == 0){
            $username = $checker->getUid();
            $connection = SQLStaticUnit::setup();
            $where_uid = SQLWhereOperatorCreator::getInterface(
                SQLColumnsBuilder::getSingleColumn('u_id')
            )->create(SQLWhereOperatorCreator::$EQUAL_TO, $username);
            $columns = SQLColumnsBuilder::getInterface()
                ->add('u_password')
                ->build();
            $command = $connection->newSQLReadCommand(
                SQLTableBuilder::buildFormExistTable('student_info')
            )->SELECT($columns)->WHERE($where_uid)->buildSQLCommand();
            $result = $connection->readSyn($command);
            if (sizeof($result) == 1) {
                return new SessionHelper($username, $result[0]['u_password']);
            }
        }
        throw new SessionHelperException('Invalid token or username.');
    }

    public function refresh(&$session = null): array {
        try {
            $result = $this->getVerifyLocation($location);
            if ($result['code'] != 200){
                return $result;
            }
            //获取ASP.NET_SessionId
            $client = CurlClientBuilder::getInterface()
                ->followLocation(false)
                ->build();
            $request = CurlRequestBuilder::getInterface()
                ->url($location)
                ->build();
            $response = $client->newCall($request)->execute();
            if (preg_match('/ASP.NET_SessionId=(.*?);/', $response->header('Set-Cookie'), $matches) == false) {
                Debug::getTrack(
                    $result, 502, '请求处理错误',
                    __DIR__, __FILE__, __LINE__, __METHOD__,
                    '$session 获取失败'
                );
                return $result;
            }

            $session = $matches[1];
            $manager = SessionManager::getInterface($this->username);
            if ($manager->checkUserExist()) {
                $manager->update($this->password, $session);
            } else {
                $manager->insert($this->password, $session);
            }
            return ['code' => 200, 'message' => 'success.'];
        } catch (CurlToolException $e){
            Debug::getTrack(
                $result, 502, '无法连接教务系统，可能由于教务系统正在维护或处于高峰期',
                __DIR__, __FILE__, __LINE__, __METHOD__,
                $e->getMessage()
            );
            return $result;
        }
    }

    public function getVerifyLocation(string &$location = null): array {
        //获取第1个JSESSIONID和lt
        $client = CurlClientBuilder::getInterface()
            ->followLocation(false)
            ->build();
        $request = CurlRequestBuilder::getInterface()
            ->url('http://218.6.163.95:18080/zfca/login')
            ->build();
        $response = $client->newCall($request)->execute();
        if (preg_match('/lt" value="(.*?)"/', $response->body(), $matches)) {
            $lt = $matches[1];
        } else {
            Debug::getTrack(
                $result, 502, '请求处理错误',
                __DIR__, __FILE__, __LINE__, __METHOD__,
                '$lt 获取失败'
            );
            return $result;
        }
        if (preg_match('/JSESSIONID=(.*?);/', $response->header('Set-Cookie'), $matches)) {
            $JSESSIONID1 = $matches[1];
        } else {
            Debug::getTrack(
                $result, 502, '请求处理错误',
                __DIR__, __FILE__, __LINE__, __METHOD__,
                '$JSESSIONID1 获取失败'
            );
            return $result;
        }

        //解密密码
        $password_decode = RSAStaticUnit::decodePublicEncode($this->password);
        if ($password_decode == false) {
            Debug::getTrack(
                $result, 406, '密码解密失败',
                __DIR__, __FILE__, __LINE__, __METHOD__,
                '密码解密失败'
            );
            return $result;
        }
        //POST账号密码-第1次跳转
        $form = FormBodyBuilder::getInterface()
            ->add('useValidateCode', 0)
            ->add('isremenberme', 0)
            ->add('ip')
            ->add('username', $this->username)
            ->add('password', $password_decode)
            ->add('losetime', 30)
            ->add('lt', $lt)
            ->add('_eventId', 'submit')
            ->add('submit1', '+')
            ->build();
        $request = CurlRequestBuilder::getInterface()
            ->url("http://218.6.163.95:18080/zfca/login;jsessionid=$JSESSIONID1")
            ->addCookie('JSESSIONID', $JSESSIONID1)
            ->post($form)
            ->build();
        $response = $client->newCall($request)->execute();
        if (!preg_match('/CASTGC=(.*?);/', $response->header('Set-Cookie'), $matches)) {
            SessionManager::getInterface($this->username)->markTokenInvalid();
            Debug::getTrack(
                $result, 406, '账号或密码错误',
                __DIR__, __FILE__, __LINE__, __METHOD__,
                '账号或密码错误'
            );
            return $result;
        } else {
            $CASTGC = $matches[1];
            $location = $response->header('Location');
            if ($location == null) {
                Debug::getTrack(
                    $result, 502, '请求处理错误',
                    __DIR__, __FILE__, __LINE__, __METHOD__,
                    '第一次跳转失败'
                );
                return $result;
            }
        }

        //第2个JSESSIONID-第2次跳转
        $request = CurlRequestBuilder::getInterface()
            ->url($location)
            ->build();
        $response = $client->newCall($request)->execute();
        if (preg_match('/JSESSIONID=(.*?);/', $response->header('Set-Cookie'), $matches)) {
            $JSESSIONID2 = $matches[1];
            $location = $response->header('Location');
            if ($location == null) {
                Debug::getTrack(
                    $result, 502, '请求处理错误',
                    __DIR__, __FILE__, __LINE__, __METHOD__,
                    '第二次跳转失败'
                );
                return $result;
            }
        } else {
            Debug::getTrack(
                $result, 502, '请求处理错误',
                __DIR__, __FILE__, __LINE__, __METHOD__,
                '$JSESSIONID2 获取失败'
            );
            return $result;
        }

        //获取身份标识-第3次跳转
        $request = CurlRequestBuilder::getInterface()
            ->url($location)
            ->addCookie('JSESSIONID', $JSESSIONID1)
            ->addCookie('CASTGC', $CASTGC)
            ->addCookie('JSESSIONID', $JSESSIONID2)
            ->build();
        $response = $client->newCall($request)->execute();
        $location = $response->header('Location');
        if ($location == null) {
            Debug::getTrack(
                $result, 502, '请求处理错误',
                __DIR__, __FILE__, __LINE__, __METHOD__,
                '第三次跳转失败'
            );
            return $result;
        }

        //获取账号身份
        $request = CurlRequestBuilder::getInterface()
            ->url($location)
            ->addCookie('JSESSIONID', $JSESSIONID2)
            ->build();
        $response = $client->newCall($request)->execute();
        if (preg_match('/student/', $response->body(), $matches)) {
            $identity = 'student';
        } else if (preg_match('/teacher/', $response->body(), $matches)) {
            $identity = 'teacher';
        } else {
            Debug::getTrack(
                $result, 502, '请求处理错误',
                __DIR__, __FILE__, __LINE__, __METHOD__,
                '$identity 获取失败'
            );
            return $result;
        }

        //登录教务系统
        $location = "http://218.6.163.95:18080/zfca/login?yhlx=$identity&login=0122579031373493708&url=xs_main.aspx";
        $request = CurlRequestBuilder::getInterface()
            ->url($location)
            ->addCookie('JSESSIONID', $JSESSIONID1)
            ->addCookie('CASTGC', $CASTGC)
            ->addCookie('JSESSIONID', $JSESSIONID2)
            ->build();
        $response = $client->newCall($request)->execute();
        $location = $response->header('Location');
        if ($location == null) {
            Debug::getTrack(
                $result, 502, '请求处理错误',
                __DIR__, __FILE__, __LINE__, __METHOD__,
                '登录教务系统失败'
            );
            return $result;
        }
        return ['code' => 200, 'message' => 'success.'];
    }

    public function get(string &$session = null): array {
        $manager = SessionManager::getInterface($this->username);
        $result = $manager->get($this->password, $session);
        if ($result['code'] == 404 or $result['code'] == 204) {
            $result = $this->refresh($session);
        } else {
            $client = CurlClientBuilder::getInterface()
                ->followLocation(false)
                ->build();
            $request = CurlRequestBuilder::getInterface()
                ->url("http://218.6.163.93:8081/xs_main.aspx?xh=".$this->username."&type=1")
                ->addCookie('ASP.NET_SessionID', $session)
                ->build();
            $response = $client->newCall($request)->execute();
            if (!preg_match('/__VIEWSTATE" value="(.*?)"/', $response->body(), $matches)) {
                $result = $this->refresh($session);
            }
        }
        return $result;
    }

    function onReadFinish(int $request_id, array $result): void { }

    function onQueryFailed(int $request_id, SQLException $e): void { }
}

class SessionHelperException extends Exception { }