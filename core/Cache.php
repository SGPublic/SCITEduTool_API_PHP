<?php

namespace sgpublic\scit\tool\core;

class Cache {
    private int $expired = 600;
    private string $cache_dir = __FILE__ . '/base/cache/';

    private int $expired_result = -1;
    private string $content_result;

    public function __construct($cache_name, $arg_array) {
        $this->expired = time() + $this->expired;

        $this->cache_dir = "$this->cache_dir$cache_name";
        sort($arg_array);
        foreach ($arg_array as $x) {
            if ($cache_name != $x) {
                $this->cache_dir = $this->cache_dir . "/$x";
            }
        }

        $explores = explode('/', $this->cache_dir);
        $dir = "";
        for ($i = 0; $i < sizeof($explores); $i++){
            if ($i < sizeof($explores) - 1){
                $dir = $dir . $explores[$i] . "/";
            }
        }
        if (!is_dir($dir)){
            mkdir($dir, 0777, true);
        }
        if (is_file("$this->cache_dir.php")){
            include "Cache.php";
        }
    }

    public function save($cache_content = "") {
        $cache_file = fopen("$this->cache_dir.php","w");
        $data = "<?php"
            . "\n\$this->expired_result = $this->expired;"
            . "\n\$this->content_result = '$cache_content';"
            . "\nhttp_response_code(404);";
        fwrite($cache_file, $data);
        fclose($cache_file);
    }

    public function getExpired() {
        if ($this->expired_result != -1) {
            if ($this->read()) {
                $this->save($this->read());
            }
            return $this->expired_result > time();
        } else {
            return false;
        }
    }

    public function read(): string {
        if ($this->content_result != null){
            return $this->content_result;
        } else {
            return false;
        }
    }
}