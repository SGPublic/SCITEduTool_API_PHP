<?php


namespace sgpublic\scit\tool\helper;

use sgpublic\scit\tool\core\Debug;
use sgpublic\scit\tool\rsa\RSAStaticUnit;
use sgpublic\scit\tool\sql\SQLColumnsBuilder;
use sgpublic\scit\tool\sql\SQLConnection;
use sgpublic\scit\tool\sql\SQLException;
use sgpublic\scit\tool\sql\SQLTable;
use sgpublic\scit\tool\sql\SQLWhereOperator;
use sgpublic\scit\tool\sql\SQLInsertActionBuilder;
use sgpublic\scit\tool\sql\SQLTableBuilder;
use sgpublic\scit\tool\sql\SQLUpdateActionBuilder;
use sgpublic\scit\tool\sql\SQLWhereOperatorCreator;

class SessionManager {
    private SQLConnection $connection;
    private SQLTable $table_student_info;
    private SQLWhereOperator $where_uid;
    private string $username;

    private function __construct(string $uid) {
        $this->connection = SQLStaticUnit::setup();
        $this->table_student_info = SQLTableBuilder::buildFormExistTable('student_info');
        $this->where_uid = SQLWhereOperatorCreator::getInterface(
            SQLColumnsBuilder::getSingleColumn('u_id')
        )->create(SQLWhereOperatorCreator::$EQUAL_TO, $uid);
        $this->username = $uid;
    }

    public static function getInterface(string $uid): SessionManager {
        return new SessionManager($uid);
    }

    public function checkUserExist(): bool {
        $columns = SQLColumnsBuilder::getInterface()
            ->add('u_id')->add('u_password')
            ->build();
        $command = $this->connection->newSQLReadCommand($this->table_student_info)
            ->SELECT($columns)->WHERE($this->where_uid)
            ->buildSQLCommand();
        $result = $this->connection->readSyn($command);
        return sizeof($result) == 1 and $result[0]['u_id'] == $this->username;
    }

    public function markTokenInvalid(){
        $update_action = SQLUpdateActionBuilder::getInterface()
            ->WHERE($this->where_uid)
            ->SET('u_token_effective', false)
            ->build();
        $command = $this->connection->newSQLWriteCommand($this->table_student_info)
            ->UPDATE($update_action);
        return $this->connection->writeSyn($command);
    }

    public function update(string $password, string $session): array {
        $update_action = SQLUpdateActionBuilder::getInterface()
            ->WHERE($this->where_uid)
            ->SET('u_password', $password)
            ->SET('u_session', $session)
            ->SET('u_session_expired', time() + 1790)
            ->SET('u_token_effective', true)
            ->build();
        $command = $this->connection->newSQLWriteCommand($this->table_student_info)
            ->UPDATE($update_action);
        return $this->connection->writeSyn($command);
    }

    public function insert(string $password, string $session): array {
        $insert_action = SQLInsertActionBuilder::getInterface()
            ->addWithColumnName('u_id', $this->username)
            ->addWithColumnName('u_password', $password)
            ->addWithColumnName('u_session', $session)
            ->addWithColumnName('u_session_expired', time() + 1790)
            ->addWithColumnName('u_token_effective', true)
            ->build();
        $command = $this->connection->newSQLWriteCommand($this->table_student_info)
            ->INSERT_INTO($insert_action);
        return $this->connection->writeSyn($command);
    }

    public function get(string $password, &$session = null): array {
        try {
            $where_uid = SQLWhereOperatorCreator::getInterface(
                SQLColumnsBuilder::getSingleColumn('u_id')
            )->create(SQLWhereOperatorCreator::$EQUAL_TO, $this->username);
            $columns = SQLColumnsBuilder::getInterface()
                ->add('u_password')
                ->add('u_session')
                ->add('u_session_expired')
                ->build();
            $connection = SQLStaticUnit::setup();
            $command = $connection->newSQLReadCommand(
                SQLTableBuilder::buildFormExistTable('student_info')
            )->SELECT($columns)->WHERE($where_uid)->buildSQLCommand();
            $result = $connection->readSyn($command);
            $password_decode = RSAStaticUnit::decodePublicEncode($password);
            if (sizeof($result) == 1){
                $password = RSAStaticUnit::decodePublicEncode($result[0]['u_password']);
                if ($password == $password_decode) {
                    if (time() <= $result[0]['u_session_expired']){
                        $session = $result[0]['u_session'];
                        return ['code' => 200, 'message' => 'success.'];
                    } else {
                        Debug::getTrack(
                            $result, 204, '处理中',
                            __DIR__, __FILE__, __LINE__, __METHOD__,
                            'ASP.NET_SessionId过期'
                        );
                        return $result;
                    }
                } else {
                    Debug::getTrack(
                        $result, 204, '处理中',
                        __DIR__, __FILE__, __LINE__, __METHOD__,
                        '密码有更改'
                    );
                    return $result;
                }
            } else {
                Debug::getTrack(
                    $result, 404, '处理中',
                    __DIR__, __FILE__, __LINE__, __METHOD__,
                    '用户信息不存在'
                );
                return $result;
            }
        } catch (SQLException $e) {
            Debug::getTrack(
                $result, 500, '服务器内部错误',
                __DIR__, __FILE__, __LINE__, __METHOD__,
                $e->getMessage()
            );
            return $result;
        }
    }
}