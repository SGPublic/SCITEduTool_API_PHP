<?php

namespace sgpublic\scit\tool\helper;

require SCIT_EDU_TOOL_ROOT."/manager/ChartManager.php";

use sgpublic\scit\tool\sql\SQLColumnsBuilder;
use sgpublic\scit\tool\sql\SQLConnection;
use sgpublic\scit\tool\sql\SQLException;
use sgpublic\scit\tool\sql\SQLTable;
use sgpublic\scit\tool\sql\SQLWhereOperator;
use sgpublic\scit\tool\sql\SQLTableBuilder;
use sgpublic\scit\tool\sql\SQLUpdateActionBuilder;
use sgpublic\scit\tool\sql\SQLWhereOperatorCreator;
use sgpublic\scit\tool\token\TokenChecker;

class InfoManager {
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

    public static function getInterface(TokenChecker $checker): InfoManager {
        return new InfoManager($checker->getUid());
    }

    public function get(array &$info = null): bool {
        $columns = SQLColumnsBuilder::getInterface()
            ->add('u_name')->add('u_faculty')
            ->add('u_specialty')
            ->add('u_class')->add('u_grade')
            ->build();
        $command = $this->connection->newSQLReadCommand($this->table_student_info)
            ->SELECT($columns)->WHERE($this->where_uid)
            ->buildSQLCommand();
        $result = $this->connection->readSyn($command);
        if (sizeof($result) == 1){
            $f_id = $result[0]['u_faculty'];
            $s_id = $result[0]['u_specialty'];
            $c_id = $result[0]['u_class'];
            if ($f_id == null or $s_id == null or $c_id == null){
                return false;
            }
            if (ChartManager::getFacultyName($f_id, $f_name)
                and ChartManager::getSpecialtyName($f_id, $s_id, $s_name)
                and ChartManager::getClassName($f_id, $s_id, $c_id, $c_name)){
                $info['name'] = $result[0]['u_name'];
                $info['faculty'] = [
                    'name' => $f_name,
                    'id' => intval($f_id)
                ];
                $info['specialty'] = [
                    'name' => $s_name,
                    'id' => intval($s_id)
                ];
                $info['class'] = [
                    'name' => $c_name,
                    'id' => intval($c_id)
                ];
                $info['grade'] = intval($result[0]['u_grade']);
                return true;
            } else {
                return false;
            }
        } else {
            return false;
        }
    }

    public function update(string $u_name, int $u_faculty,
        int $u_specialty, int $u_class, int $u_grade): bool {
        $update_action = SQLUpdateActionBuilder::getInterface()
            ->WHERE($this->where_uid)
            ->SET('u_name', $u_name)
            ->SET('u_faculty', $u_faculty)
            ->SET('u_specialty', $u_specialty)
            ->SET('u_class', $u_class)
            ->SET('u_grade', $u_grade)
            ->build();
        $command = $this->connection->newSQLWriteCommand($this->table_student_info)
            ->UPDATE($update_action);
        try {
            $result = $this->connection->writeSyn($command);
            return $result[0] == 0;
        } catch (SQLException $e) {
            return false;
        }
    }
}