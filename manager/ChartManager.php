<?php


namespace sgpublic\scit\tool\helper;


use sgpublic\scit\tool\sql\SQLColumnsBuilder;
use sgpublic\scit\tool\sql\SQLException;
use sgpublic\scit\tool\sql\SQLInsertActionBuilder;
use sgpublic\scit\tool\sql\SQLTableBuilder;
use sgpublic\scit\tool\sql\SQLUpdateActionBuilder;
use sgpublic\scit\tool\sql\SQLWhereOperatorCreator;

class ChartManager {
    public static function getFacultyName(int $f_id, string &$name = null): bool {
        try {
            $connection = SQLStaticUnit::setup();
            $command = $connection->newSQLReadCommand(
                SQLTableBuilder::buildFormExistTable('faculty_chart')
            )->SELECT(
                SQLColumnsBuilder::getSingleColumn('f_name')
            )->WHERE(
                SQLWhereOperatorCreator::getInterface(
                    SQLColumnsBuilder::getSingleColumn('f_id')
                )->create(SQLWhereOperatorCreator::$EQUAL_TO, $f_id)
            )->buildSQLCommand();
            $result = $connection->readSyn($command);
            if (sizeof($result) == 1 and isset($result[0]['f_name'])){
                $name = $result[0]['f_name'];
                return true;
            } else {
                return false;
            }
        } catch (SQLException $e) {
            return false;
        }
    }

    public static function getSpecialtyName(int $f_id, int $s_id, string &$name = null): bool {
        try {
            $connection = SQLStaticUnit::setup();
            $table = SQLTableBuilder::buildFormExistTable('specialty_chart');
            $command = $connection->newSQLReadCommand($table)->SELECT(
                SQLColumnsBuilder::getSingleColumn('s_name')
            )->WHERE(
                SQLWhereOperatorCreator::getInterface(
                    SQLColumnsBuilder::getSingleColumn('f_id')
                )->create(SQLWhereOperatorCreator::$EQUAL_TO, $f_id)->AND(
                    SQLWhereOperatorCreator::getInterface(
                        SQLColumnsBuilder::getSingleColumn('s_id')
                    )->create(SQLWhereOperatorCreator::$EQUAL_TO, $s_id)
                )
            )->buildSQLCommand();
            $result = $connection->readSyn($command);
            if (sizeof($result) == 1 and isset($result[0]['s_name'])){
                $name = $result[0]['s_name'];
                return true;
            } else {
                return false;
            }
        } catch (SQLException $e) {
            return false;
        }
    }

    public static function getClassName(int $f_id, int $s_id, int $c_id, string &$name = null): bool {
        try {
            $connection = SQLStaticUnit::setup();
            $table = SQLTableBuilder::buildFormExistTable('class_chart');
            $command = $connection->newSQLReadCommand($table)->SELECT(
                SQLColumnsBuilder::getSingleColumn('c_name')
            )->WHERE(
                SQLWhereOperatorCreator::getInterface(
                    SQLColumnsBuilder::getSingleColumn('f_id')
                )->create(SQLWhereOperatorCreator::$EQUAL_TO, $f_id)->AND(
                    SQLWhereOperatorCreator::getInterface(
                        SQLColumnsBuilder::getSingleColumn('s_id')
                    )->create(SQLWhereOperatorCreator::$EQUAL_TO, $s_id)
                )->AND(
                    SQLWhereOperatorCreator::getInterface(
                        SQLColumnsBuilder::getSingleColumn('c_id')
                    )->create(SQLWhereOperatorCreator::$EQUAL_TO, $c_id)
                )
            )->buildSQLCommand();
            $result = $connection->readSyn($command);
            if (sizeof($result) == 1 and isset($result[0]['c_name'])){
                $name = $result[0]['c_name'];
                return true;
            } else {
                return false;
            }
        } catch (SQLException $e) {
            return false;
        }
    }

    public static function getCharsetIDWithClassName(string $class_name, int &$lbl_xy_id = null, int &$lbl_zymc_id = null): bool {
        try {
            $connection = SQLStaticUnit::setup();
            $table = SQLTableBuilder::buildFormExistTable('class_chart');
            $command = $connection->newSQLReadCommand($table)->SELECT(
                SQLColumnsBuilder::getInterface()
                    ->add('f_id')->add('s_id')
                    ->build()
            )->WHERE(
                SQLWhereOperatorCreator::getInterface(
                    SQLColumnsBuilder::getSingleColumn('c_name')
                )->create(SQLWhereOperatorCreator::$EQUAL_TO, $class_name)
            )->buildSQLCommand();
            $result = $connection->readSyn($command);
            if (sizeof($result) == 1){
                $lbl_xy_id = $result[0]['f_id'];
                $lbl_zymc_id = $result[0]['s_id'];
                return true;
            } else {
                return false;
            }
        } catch (SQLException $e) {
            return false;
        }
    }

    private static function writeFacultyName(int $f_id, string $name): bool {
        try {
            $exist = self::getFacultyName($f_id, $f_name);
            $table = SQLTableBuilder::buildFormExistTable('faculty_chart');
            $connection = SQLStaticUnit::setup();
            if ($exist){
                if ($f_name == $name){
                    return true;
                }
                $command = $connection->newSQLWriteCommand($table)->UPDATE(
                    SQLUpdateActionBuilder::getInterface()->WHERE(
                        SQLWhereOperatorCreator::getInterface(
                            SQLColumnsBuilder::getSingleColumn('f_id')
                        )->create(SQLWhereOperatorCreator::$EQUAL_TO, $f_id)
                    )->SET('f_name', $name)->build()
                );
            } else {
                $command = $connection->newSQLWriteCommand($table)->INSERT_INTO(
                    SQLInsertActionBuilder::getInterface()
                        ->addSimplify($f_id)
                        ->addSimplify($name)
                        ->build()
                );
            }
            $result = $connection->writeSyn($command);
            return $result[0] == 0;
        } catch (SQLException $e) {
            echo $e->getMessage();
            return false;
        }
    }

    private static function writeSpecialtyName(int $f_id, int $s_id, string $name): bool {
        try {
            $exist = self::getSpecialtyName($f_id, $s_id, $s_name);
            $table = SQLTableBuilder::buildFormExistTable('specialty_chart');
            $connection = SQLStaticUnit::setup();
            if ($exist){
                if ($s_name == $name){
                    return true;
                }
                $command = $connection->newSQLWriteCommand($table)->UPDATE(
                    SQLUpdateActionBuilder::getInterface()->WHERE(
                        SQLWhereOperatorCreator::getInterface(
                            SQLColumnsBuilder::getSingleColumn('f_id')
                        )->create(SQLWhereOperatorCreator::$EQUAL_TO, $f_id)->AND(
                            SQLWhereOperatorCreator::getInterface(
                                SQLColumnsBuilder::getSingleColumn('s_id')
                            )->create(SQLWhereOperatorCreator::$EQUAL_TO, $s_id)
                        )
                    )->SET('s_name', $name)->build()
                );
            } else {
                $command = $connection->newSQLWriteCommand($table)->INSERT_INTO(
                    SQLInsertActionBuilder::getInterface()
                        ->addSimplify($s_id)
                        ->addSimplify($name)
                        ->addSimplify($f_id)
                        ->build()
                );
            }
            $result = $connection->writeSyn($command);
            return $result[0] == 0;
        } catch (SQLException $e) {
            return false;
        }
    }

    private static function writeClassName(int $f_id, int $s_id, int $c_id, string $name): bool {
        try {
            $exist = self::getClassName($f_id, $s_id, $c_id, $s_name);
            $table = SQLTableBuilder::buildFormExistTable('class_chart');
            $connection = SQLStaticUnit::setup();
            if ($exist){
                if ($s_name == $name){
                    return true;
                }
                $command = $connection->newSQLWriteCommand($table)->UPDATE(
                    SQLUpdateActionBuilder::getInterface()->WHERE(
                        SQLWhereOperatorCreator::getInterface(
                            SQLColumnsBuilder::getSingleColumn('f_id')
                        )->create(SQLWhereOperatorCreator::$EQUAL_TO, $f_id)->AND(
                            SQLWhereOperatorCreator::getInterface(
                                SQLColumnsBuilder::getSingleColumn('s_id')
                            )->create(SQLWhereOperatorCreator::$EQUAL_TO, $s_id)
                        )->AND(
                            SQLWhereOperatorCreator::getInterface(
                                SQLColumnsBuilder::getSingleColumn('c_id')
                            )->create(SQLWhereOperatorCreator::$EQUAL_TO, $c_id)
                        )
                    )->SET('c_name', $name)->build()
                );
            } else {
                $command = $connection->newSQLWriteCommand($table)->INSERT_INTO(
                    SQLInsertActionBuilder::getInterface()
                        ->addSimplify($f_id)
                        ->addSimplify($s_id)
                        ->addSimplify($c_id)
                        ->addSimplify($name)
                        ->build()
                );
            }
            $result = $connection->writeSyn($command);
            return $result[0] == 0;
        } catch (SQLException $e) {
            return false;
        }
    }

    public static function writeChart(int $f_id, string $f_name, int $s_id,
        string $s_name, int $c_id, string $c_name): bool {
        $f_result = self::writeFacultyName($f_id, $f_name);
        $s_result = self::writeSpecialtyName($f_id, $s_id, $s_name);
        $c_result = self::writeClassName($f_id, $s_id, $c_id, $c_name);
        return $f_result and $s_result and $c_result;
    }
}