<?php


namespace sgpublic\scit\tool\helper;

use sgpublic\scit\tool\sql\SQLConnectHelper;
use sgpublic\scit\tool\sql\SQLConnection;

require SCIT_EDU_TOOL_ROOT . '/unit/SQLUnit.php';

class SQLStaticUnit {
    public static function setup(): SQLConnection {
        return SQLConnectHelper::getInterface()->setup(
            'localhost', 'root', '020821sky..', 'scit_edu_tool'
        );
    }
}