<?php


namespace sgpublic\scit\tool\helper;

use sgpublic\scit\tool\sql\SQLColumnsBuilder;
use sgpublic\scit\tool\sql\SQLConnection;
use sgpublic\scit\tool\sql\SQLException;
use sgpublic\scit\tool\sql\SQLInsertActionBuilder;
use sgpublic\scit\tool\sql\SQLTable;
use sgpublic\scit\tool\sql\SQLTableBuilder;
use sgpublic\scit\tool\sql\SQLUpdateActionBuilder;
use sgpublic\scit\tool\sql\SQLWhereOperator;
use sgpublic\scit\tool\sql\SQLWhereOperatorCreator;
use sgpublic\scit\tool\token\TokenChecker;

class TableManager {
    private SQLConnection $connection;
    private SQLTable $table_class_schedule;
    private SQLWhereOperator $where_tid;
    private array $t_info;
    private string $tid;
    private string $year;
    private int $semester;

    private function __construct(array $t_info, string $year, int $semester) {
        $this->connection = SQLStaticUnit::setup();
        $this->table_class_schedule = SQLTableBuilder::buildFormExistTable('class_schedule');
        $this->t_info = $t_info;
        $this->tid = TableHelper::getTableID($t_info, $year, $semester);
        $this->where_tid = SQLWhereOperatorCreator::getInterface(
            SQLColumnsBuilder::getSingleColumn('t_id')
        )->create(SQLWhereOperatorCreator::$EQUAL_TO,$this->tid);
        $this->year = $year;
        $this->semester = $semester;
    }

    public static function getInterface(TokenChecker $checker, array $info, string $year, int $semester): TableManager {
        if ($checker->check() == 0) {
            return new TableManager($info, $year, $semester);
        }
        throw new SessionHelperException('Invalid token or username.');
    }

    public function checkTableExit(): bool {
        $columns = SQLColumnsBuilder::getInterface()
            ->add('t_id')
            ->build();
        $command = $this->connection->newSQLReadCommand($this->table_class_schedule)
            ->SELECT($columns)->WHERE($this->where_tid)
            ->buildSQLCommand();
        $result = $this->connection->readSyn($command);
        return sizeof($result) == 1;
    }

    public function insert(array $table): bool {
        $insert_action = SQLInsertActionBuilder::getInterface()
            ->addSimplify($this->tid)
            ->addSimplify($this->t_info['faculty']['id'])
            ->addSimplify($this->t_info['specialty']['id'])
            ->addSimplify($this->t_info['class']['id'])
            ->addSimplify($this->t_info['grade'])
            ->addSimplify($this->year)
            ->addSimplify($this->semester)
            ->addSimplify(json_encode($table, 320))
            ->addSimplify(time() + 86400)
            ->build();
        $command = $this->connection->newSQLWriteCommand($this->table_class_schedule)
            ->INSERT_INTO($insert_action);
        try {
            $result = $this->connection->writeSyn($command);
            return $result[0] == 0;
        } catch (SQLException $e) {
            return false;
        }
    }

    public function update(array $table): bool {
        $update_action = SQLUpdateActionBuilder::getInterface()
            ->WHERE($this->where_tid)
            ->SET('t_content', json_encode($table, 320))
            ->SET('t_expired', time() + 86400)
            ->build();
        $command = $this->connection->newSQLWriteCommand($this->table_class_schedule)
            ->UPDATE($update_action);
        try {
            $result = $this->connection->writeSyn($command);
            return $result[0] == 0;
        } catch (SQLException $e) {
            return false;
        }
    }

    public function get(array &$table = null): bool {
        $columns = SQLColumnsBuilder::getInterface()
            ->add('t_content')
            ->add('t_expired')
            ->build();
        $command = $this->connection->newSQLReadCommand($this->table_class_schedule)
            ->SELECT($columns)->WHERE($this->where_tid)
            ->buildSQLCommand();
        $result = $this->connection->readSyn($command);
        if (sizeof($result) == 1){
            if ($result[0]['t_expired'] < time()){
                return false;
            } else {
                $table = json_decode($result[0]['t_content'], 320);
                return true;
            }
        } else {
            return false;
        }
    }
}