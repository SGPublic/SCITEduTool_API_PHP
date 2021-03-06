<?php

namespace sgpublic\scit\tool;

define('SCIT_EDU_TOOL_ROOT', dirname(__FILE__));
require SCIT_EDU_TOOL_ROOT."/base/NormalAPI.php";

use sgpublic\scit\tool\base\NormalAPI;
use sgpublic\scit\tool\core\Verify;
use sgpublic\scit\tool\curl\CurlClientBuilder;
use sgpublic\scit\tool\curl\CurlRequestBuilder;

class hitokoto extends NormalAPI {
    /** @noinspection PhpMissingParentConstructorInspection */
    public function __construct() {
        $client = CurlClientBuilder::getInterface()
            ->followLocation(false)
            ->build();
        $request = CurlRequestBuilder::getInterface()
            ->url('https://v1.hitokoto.cn/?encode=json')
            ->build();
        $response = $client->newCall($request)->execute();
        $sentence_data = json_decode($response->body(), 320);
        echo json_encode([
            'code' => 0,
            'message' => 'success.',
            'string' => $sentence_data['hitokoto'],
            'from' => $sentence_data['from'],
        ], 320);
    }

    protected function API(Verify $sign){ }
}

new hitokoto();