<?php

namespace sgpublic\scit\tool\token;

class StaticUnit {
    /**
     * token 签名校验用的 AppSecret
     *
     * @return string AppSecret
     */
    public static function getAppSecret(): string{
        return 'fcb1eb98faf99495';
    }

    /**
     * 构建 token 的 header 部分
     *
     * @param array $attrs token 中附带的参数序列
     * @return array 返回 header 及其源文本
     */
    public static function buildHeader(array $attrs): array {
        $header_string = "";
        foreach ($attrs as $key => $value){
            if ($header_string != ""){
                $header_string = "$header_string&";
            }
            $header_string = "$header_string$key=$value";
        }
        return [
            substr(md5($header_string), 8, 16),
            $header_string
        ];
    }

    /**
     * 构建 token 的 body 部分
     *
     * @param string $uid token 需要附带的 uid 信息
     * @param int $expired
     * @param int $expired_unit
     * @return array
     */
    public static function buildBody(string $uid, int $time_now, int $expired, int $expired_unit): array {
        $body_string = "$uid&$time_now&$expired&$expired_unit";
        for ($i = 0; $i < strlen($body_string) % 3; $i++){
            $body_string = "$body_string=";
        }
        return [base64_encode($body_string), $body_string];
    }
}

class TokenBuilder {
    private array $attrs = [];
    private string $uid;
    private int $access_expired = 30;
    private int $access_expired_unit = 2;
    private int $refresh_expired = 4;
    private int $refresh_expired_unit = 0;

    public function __construct(){ }

    /**
     * 设置 token 附带的 uid 信息，uid 长度应不超过 12 个字符，且唯一
     *
     * @param string $uid uid
     * @return bool 若 uid 长度超过 12 个字符则返回 false
     */
    public function setUid(string $uid): bool {
        if (strlen($uid) > 12 | strlen($uid) == 0){
            return false;
        } else {
            $this->uid = $uid;
            return true;
        }
    }

    /**
     * 设置 access_token 有效期
     *
     * @param int $expired 有效期时长
     * @param int $expired_unit 有效期时长单位
     * <br> 0 => 365天
     * <br> 1 => 30天
     * <br> 2 => 1天
     * @return bool 若有效期时长单位未按要求设置，则返回 false
     */
    public function setExpired(int $expired, int $expired_unit): bool {
        if ($expired_unit < 0 | $expired_unit > 2){
            return false;
        } else {
            $this->access_expired = $expired;
            $this->access_expired_unit = $expired_unit;
            return true;
        }
    }

    /**
     * 设置 refresh_token 有效期
     *
     * @param int $expired 有效期时长
     * @param int $expired_unit 有效期时长单位
     * <br> 0 => 365天
     * <br> 1 => 30天
     * <br> 2 => 1天
     * @return bool 若有效期时长单位未按要求设置，则返回 false
     */
    public function setRefreshExpired(int $expired, int $expired_unit): bool {
        if ($expired_unit < 0 | $expired_unit > 2){
            return false;
        } else {
            $this->refresh_expired = $expired;
            $this->refresh_expired_unit = $expired_unit;
            return true;
        }
    }

    /**
     * 添加 token 中需要附带的参数序列
     *
     * @param string $key 参数键
     * @param string $value 参数值
     * @return $this 支持链式调用
     */
    public function addAttr(string $key, string $value): TokenBuilder {
        $this->attrs[$key] = $value;
        return $this;
    }

    /**
     * 开始构建 access_token
     *
     * @return string access_token
     */
    public function build(): string {
        $time_now = time();
        $this->addAttr('time', $time_now);
        $header = StaticUnit::buildHeader($this->attrs + ['type' => 'access']);
        $body = StaticUnit::buildBody($this->uid, $time_now,
            $this->access_expired, $this->access_expired_unit);
        $app_secret = StaticUnit::getAppSecret();
        $footer = substr(md5("$header[1].$body[1].$app_secret"), 8, 16);
        return "$header[0].$body[0].$footer";
    }

    /**
     * 开始构建 refresh_token
     *
     * @return string refresh_token
     */
    public function buildRefresh(): string {
        $time_now = time();
        $this->addAttr('time', $time_now);
        $header = StaticUnit::buildHeader($this->attrs + ['type' => 'refresh']);
        $body = StaticUnit::buildBody($this->uid, $time_now,
            $this->refresh_expired, $this->refresh_expired_unit);
        $app_secret = StaticUnit::getAppSecret();
        $footer = substr(md5("$header[1].$body[1].$app_secret"), 8, 16);
        return "$body[0].$footer";
    }
}

class TokenChecker {
    private array $attrs = [];
    private string $type;
    private string $uid;
    private int $token_time;
    private int $expired;
    private int $expired_unit;
    private string $header;
    private string $footer;

    public static string $ACCESS = 'access';
    public static string $REFRESH = 'refresh';

    public function __construct(){ }

    /**
     * 识别并初始化 token
     *
     * @param string $token 提交的 token
     * @param string $type token 类型
     * @return bool 若识别并初始化成功，则返回 true，否则返回 false
     */
    public function init(string $token, string $type): bool {
        $this->type = $type;
        if (strpos($token, '.') !== false){
            $result = explode('.', $token);
            if (sizeof($result) > 2){
                $this->header = $result[0];
                $this->footer = $result[2];
                $body_string = base64_decode($result[1]);
            } else {
                $body_string = base64_decode($result[0]);
                $this->footer = $result[1];
            }
            if ($body_string == false){
                return false;
            } else {
                $body = explode("&", str_replace(
                    "=", "", $body_string
                ));
                if ($body == false) {
                    return false;
                } elseif (intval($body[3]) > 2 | intval($body[3]) < 0){
                    return false;
                } else {
                    $this->uid = $body[0];
                    $this->token_time = intval($body[1]);
                    $this->expired = intval($body[2]);
                    $this->expired_unit = intval($body[3]);
                    return true;
                }
            }
        } else {
            return false;
        }
    }

    /**
     * 获取 token 中附带的 uid 信息
     *
     * @return string uid
     */
    public function getUid(): string{
        return $this->uid;
    }

    /**
     * 添加要与 token 比较的参数序列
     *
     * @param string $key 参数键
     * @param string $value 参数值
     * @return $this 支持链式调用
     */
    public function addAttr(string $key, string $value): TokenChecker {
        $this->attrs[$key] = $value;
        return $this;
    }

    /**
     * 开始检查 token 是否有效
     *
     * @return int 返回状态码
     * 0: 有效
     * -1: 过期
     * -2: 信息不一致
     * -3: 签名校验失败
     */
    public function check(): int {
        $time_unit = [365, 30, 1];
        $expired_time = $this->token_time
            + $time_unit[$this->expired_unit] * intval($this->expired) * 86400;
        if (time() > $expired_time){
            return -1;
        } else {
            $this->addAttr('time', $this->token_time);
            $header = StaticUnit::buildHeader($this->attrs + ['type' => $this->type]);
            if (isset($this->header) != null and $this->header != $header[0]){
                return -2;
            }
            $body = StaticUnit::buildBody($this->uid, $this->token_time, $this->expired, $this->expired_unit);
            $app_secret = StaticUnit::getAppSecret();
            $footer = substr(md5("$header[1].$body[1].$app_secret"), 8, 16);
            if ($this->footer != $footer){
                return -3;
            } else {
                return 0;
            }
        }
    }
}