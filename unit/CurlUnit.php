<?php /** @noinspection PhpMissingFieldTypeInspection */

namespace sgpublic\scit\tool\curl;

use Exception;

class CurlClientBuilder {
    private bool $follow_location = true;
    private int $timeout = 30;

    public static function getInterface(): CurlClientBuilder {
        return new CurlClientBuilder();
    }

    public function followLocation(bool $follow_location): CurlClientBuilder {
        $this->follow_location = $follow_location;
        return $this;
    }

    public function timeout(int $time): CurlClientBuilder {
        $this->timeout = $time;
        return $this;
    }


    public function build(): CurlClient {
        return new CurlClient(
            $this->follow_location,
            $this->timeout
        );
    }
}

class CurlClient {
    private bool $follow_location;
    private int $timeout;

    function __construct(bool $follow_location, int $timeout){
        $this->follow_location = $follow_location;
        $this->timeout = $timeout;
    }

    function isFollowLocation(): bool {
        return $this->follow_location;
    }

    function getTimeout(): int {
        return $this->timeout;
    }

    public function newCall(CurlRequest $request): CurlCall {
        return new CurlCall($this, $request);
    }
}

class CurlRequestBuilder {
    private string $url = "";
    private array $headers = array();
    private $body = null;

    public static function getInterface(): CurlRequestBuilder {
        return new CurlRequestBuilder();
    }

    public function url(string $url): CurlRequestBuilder {
        $this->url = $url;
        if (strpos($url, 'http://218.6.163.93') !== false){
            $this->addHeader('Referer', $url);
        }
        return $this;
    }

    public function addHeader(string $key, $value): CurlRequestBuilder {
        $this->headers[$key] = strval($value);
        return $this;
    }

    public function addCookie(string $key, $value): CurlRequestBuilder {
        $value = strval($value);
        if (!isset($this->headers['Cookie'])){
            $this->headers['Cookie'] = '';
        }
        $this->headers['Cookie'] = $this->headers['Cookie']."$key=$value; ";
        return $this;
    }

    public function post(FormBody $body): CurlRequestBuilder {
        $this->body = $body->getFormBody();
        return $this;
    }

    public function build(): CurlRequest {
        return new CurlRequest($this->url, $this->headers, $this->body);
    }
}

class CurlRequest {
    private string $url;
    private array $headers;
    private $body;

    function __construct(string $url, array $headers, $body = null){
        $this->url = $url;

        $header_array = array();
        foreach ($headers as $key => $value){
            array_push($header_array, "$key: $value");
        }
        $this->headers = $header_array;

        $this->body = $body;
    }

    function getUrl(): string {
        return $this->url;
    }

    function getBody() {
        if ($this->body != null){
            return http_build_query($this->body);
//            if (is_array($this->body)){
//                return $this->body;
//            } else {
//                return http_build_query($this->body);
//            }
        } else {
            return null;
        }
    }

    function getHeaders(): array {
        return $this->headers;
    }
}

class FormBodyBuilder {
    private array $body = array();

    public static function getInterface(): FormBodyBuilder {
        return new FormBodyBuilder();
    }

    public function add(string $key, $value = ''): FormBodyBuilder {
//        if ($key == '__VIEWSTATE'){
//            $value = str_replace('+', '%2B', $value);
//            $value = str_replace('=', '%3D', $value);
//            $value = str_replace('/', '%2F', $value);
//            echo $value;
//        }
        $this->body[$key] = strval($value);
        return $this;
    }

    public function build(string $app_secret = ''): FormBody {
        $form_string = "";
        foreach ($this->body as $key => $value){
            if ($form_string != ""){
                $form_string = "$form_string&";
            }
            $form_string = "$form_string$key=$value";
        }
        if ($app_secret != ''){
            $md5_string = md5($form_string.$app_secret);
            $this->body['sign'] = $md5_string;
        }
        return new FormArrayBody($this->body);
    }

    public function buildToString(string $app_secret = ''): FormBody {
        $form_string = "";
        foreach ($this->body as $key => $value){
            if ($form_string != ""){
                $form_string = "$form_string&";
            }
            $form_string = "$form_string$key=$value";
        }
        if ($app_secret != ''){
            $md5_string = md5($form_string.$app_secret);
            $form_string = "$form_string&sign=$md5_string";
            $this->body['sign'] = $md5_string;
        }
        return new FormStringBody($form_string);
    }
}

class FormBody {
    protected $form;

    function getFormBody() {
        return $this->form;
    }
}

class FormArrayBody extends FormBody {
    function __construct(array $form_array){
        $this->form = $form_array;
    }
}

class FormStringBody extends FormBody {
    function __construct(string $form_array){
        $this->form = $form_array;
    }
}

class CurlCall {
    private $ch;

    function __construct(CurlClient $client, CurlRequest $request){
        if ($request->getUrl() == ""){
            throw new CurlUrlNotSetException("The url of this client is not set.");
        } else {
            $this->ch = curl_init();
            curl_setopt($this->ch, CURLOPT_FOLLOWLOCATION, $client->isFollowLocation() ? 1 : 0);
            curl_setopt($this->ch, CURLOPT_TIMEOUT, $client->getTimeout());
            if (stripos($request->getUrl(), "https://") !== false) {
                curl_setopt($this->ch, CURLOPT_SSL_VERIFYPEER, false);
                curl_setopt($this->ch, CURLOPT_SSL_VERIFYHOST, false);
                curl_setopt($this->ch, CURLOPT_SSLVERSION, 1);
            } else if (stripos($request->getUrl(), "http://") === false){
                throw new CurlUrlWrongFormatException("Wrong url format, please check the url.");
            }
            curl_setopt($this->ch, CURLOPT_URL, $request->getUrl());
            curl_setopt($this->ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($this->ch, CURLOPT_HEADER, true);
            curl_setopt($this->ch, CURLINFO_HEADER_OUT, true);
            if (sizeof($request->getHeaders()) != 0){
                curl_setopt($this->ch, CURLOPT_HTTPHEADER, $request->getHeaders());
            }
            if ($request->getBody() != null){
//                echo $request->getBody()."\n\n";
                curl_setopt($this->ch, CURLOPT_POST, true);
                curl_setopt($this->ch, CURLOPT_POSTFIELDS, $request->getBody());
            }
        }
    }

    public function enqueue(CurlCallback $callback, int $requestId = 0) {
        try {
            $result = $this->execute();
            $callback->onResponse($this, $result, $requestId);
        } catch (CurlToolException $e) {
            $callback->onFailure($this, $e, $requestId);
        }
    }

    public function execute(): CurlResponse {
        $exec = curl_exec($this->ch);
        if (curl_errno($this->ch) == 0){
            $curl_info_header_size = curl_getinfo($this->ch, CURLINFO_HEADER_SIZE);
            $header = substr($exec, 0, $curl_info_header_size);
            if (strpos($header, "\n") == false){
                preg_match("/:\s/", $header, $matches);
                if (sizeof($matches) == 1){
                    $http_header = [$header];
                } else {
                    $http_header = false;
                }
            } else {
                $http_header = explode("\n", substr($exec, 0, $curl_info_header_size));
            }
            $http_header_array = array();
            if ($http_header != false){
                foreach ($http_header as $item){
                    $http_header_item = explode(": ", $item, 2);
                    if (is_array($http_header_item) && sizeof($http_header_item) == 2){
                        $http_header_array[$http_header_item[0]] = $http_header_item[1];
                    }
                }
            }
            $http_body = substr($exec, $curl_info_header_size);
            if ($http_body == false){
                $http_body = '';
            }
            return new CurlResponse(
                curl_getinfo($this->ch, CURLINFO_HTTP_CODE),
                $http_header_array,
                (string)$http_body
            );
        } else {
            throw new CurlToolException(
                curl_error($this->ch), curl_errno($this->ch)
            );
        }
    }
}

interface CurlCallback {
    function onFailure(CurlCall $call, CurlToolException $exception, int $requestId);
    function onResponse(CurlCall $call, CurlResponse $response, int $requestId);
}

class CurlResponse {
    private int $code;
    private array $headers;
    private string $body;

    function __construct(int $code, array $headers, string $body){
        $this->code = $code;
        $this->headers = $headers;
        $this->body = $body;
    }

    public function code(): int {
        return $this->code;
    }

    public function header($key) {
        if (isset($this->headers[$key])){
            $header = $this->headers[$key];
            $header = str_replace("\n", '', $header);
            $header = str_replace("\r", '', $header);
            return $header;
        } else {
            return null;
        }
    }

    public function headers(): array {
        return $this->headers;
    }

    public function body(): string {
        return $this->body;
    }
}

class CurlToolException extends Exception { }

class CurlUrlNotSetException extends CurlToolException { }

class CurlUrlWrongFormatException extends CurlToolException { }

class CurlResponseReadException extends CurlToolException { }
