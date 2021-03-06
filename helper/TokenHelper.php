<?php


namespace sgpublic\scit\tool\helper;

require SCIT_EDU_TOOL_ROOT.'/unit/TokenUnit.php';
require SCIT_EDU_TOOL_ROOT.'/core/RSAStaticUnit.php';

use sgpublic\scit\tool\rsa\RSAStaticUnit;
use sgpublic\scit\tool\sql\SQLColumnsBuilder;
use sgpublic\scit\tool\sql\SQLException;
use sgpublic\scit\tool\sql\SQLTableBuilder;
use sgpublic\scit\tool\sql\SQLWhereOperatorCreator;
use sgpublic\scit\tool\token\TokenBuilder;
use sgpublic\scit\tool\token\TokenChecker;

class TokenHelper {
    private TokenChecker $checker;
    private int $code;

    public static function getInterface(string $token, string $type): TokenHelper {
        return new TokenHelper($token, $type);
    }

    private function __construct(string $token, string $type){
        try {
            $this->checker = new TokenChecker();
            $this->checker->init($token, $type);
            $connection = SQLStaticUnit::setup();
            $where_uid = SQLWhereOperatorCreator::getInterface(
                SQLColumnsBuilder::getSingleColumn('u_id')
            )->create(SQLWhereOperatorCreator::$EQUAL_TO, $this->checker->getUid());
            $columns = SQLColumnsBuilder::getInterface()
                ->add('u_password')
                ->add('u_token_effective')
                ->build();
            $command = $connection->newSQLReadCommand(
                SQLTableBuilder::buildFormExistTable('student_info')
            )->SELECT($columns)->WHERE($where_uid)->buildSQLCommand();
            $result = $connection->readSyn($command);
            if (sizeof($result) == 1){
                if ($result[0]['u_token_effective'] === false){
                    $this->code = -4;
                } else {
                    $this->checker->addAttr('password', RSAStaticUnit::decodePublicEncode(
                        $result[0]['u_password']
                    ));
                    $this->code = $this->checker->check();
                }
            } else {
                $this->code = -5;
            }
        } catch (SQLException $e) {
            $this->code = -6;
        }
    }

    public function check(): int {
        return $this->code;
    }

    public function getChecker(): TokenChecker {
        return $this->checker;
    }

    public function refresh(string $refresh_token): array {
        $connection = SQLStaticUnit::setup();
        $where_uid = SQLWhereOperatorCreator::getInterface(
            SQLColumnsBuilder::getSingleColumn('u_id')
        )->create(SQLWhereOperatorCreator::$EQUAL_TO, $this->checker->getUid());
        $columns = SQLColumnsBuilder::getInterface()
            ->add('u_password')
            ->build();
        $command = $connection->newSQLReadCommand(
            SQLTableBuilder::buildFormExistTable('student_info')
        )->SELECT($columns)->WHERE($where_uid)->buildSQLCommand();
        $result = $connection->readSyn($command);
        if (sizeof($result) == 1){
            return self::build($this->checker->getUid(), $result[0]['u_password'], $refresh_token);
        } else {
            $result = ['code' => 403, 'message' => 'access_token无效'];
        }
        return $result;
    }

    public static function build(string $username, string $password, string $refresh_token = null){
        $builder = new TokenBuilder();
        $builder->setUid($username);
        $builder->addAttr('password', RSAStaticUnit::decodePublicEncode($password));
        $builder->setExpired(1, 1);
        $builder->setRefreshExpired(0, 4);
        if ($refresh_token == null){
            $refresh_token = $builder->buildRefresh();
        }
        return [
            'code' => 200, 'message' => 'success.',
            'access_token' => $builder->build(),
            'refresh_token' => $refresh_token
        ];
    }
}