<?php

namespace sgpublic\scit\tool;

define('SCIT_EDU_TOOL_ROOT', dirname(__FILE__));
require SCIT_EDU_TOOL_ROOT.'/base/NormalAPI.php';

use sgpublic\scit\tool\base\NormalAPI;
use sgpublic\scit\tool\core\Verify;
use sgpublic\scit\tool\helper\TokenHelper;
use sgpublic\scit\tool\helper\SessionHelper;

class login extends NormalAPI {
    public function __construct(array $args) {
        parent::__construct($args);
    }

    protected function API(Verify $sign) {
        $helper = SessionHelper::getInterface(
            $sign->getParameter('username'),
            $sign->getParameter('password')
        );
        $this->result = $helper->get();
        if ($this->result['code'] != 200){
            return;
        }
        $this->result = $this->result + TokenHelper::build(
            $sign->getParameter('username'),
            $sign->getParameter('password')
        );
    }
}

new login([
    'username' => null,
    'password' => null
]);