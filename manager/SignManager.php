<?php

namespace sgpublic\scit\tool\helper;

use sgpublic\scit\tool\sql\SQLColumnsBuilder;
use sgpublic\scit\tool\sql\SQLTableBuilder;
use sgpublic\scit\tool\sql\SQLWhereOperatorCreator;

class SignManager {
    public static function getAppSecretByAppKey(string $app_key, string $platform, string &$app_secret = null): bool {
        $where = SQLWhereOperatorCreator::getInterface(
            SQLColumnsBuilder::getSingleColumn('app_key')
        )->create(SQLWhereOperatorCreator::$EQUAL_TO, $app_key);
        $where = SQLWhereOperatorCreator::getInterface(
            SQLColumnsBuilder::getSingleColumn('platform')
        )->create(SQLWhereOperatorCreator::$EQUAL_TO, $platform)->AND($where);
        $where = SQLWhereOperatorCreator::getInterface(
            SQLColumnsBuilder::getSingleColumn('available')
        )->create(SQLWhereOperatorCreator::$EQUAL_TO, 1)->AND($where);
        $columns = SQLColumnsBuilder::getInterface()
            ->add('app_secret')
            ->build();
        $connection = SQLStaticUnit::setup();
        $command = $connection->newSQLReadCommand(
            SQLTableBuilder::buildFormExistTable('sign_keys')
        )->SELECT($columns)->WHERE($where)->buildSQLCommand();
        $result = $connection->readSyn($command);
        if (sizeof($result) == 0){
            return false;
        }
        $app_secret = $result[0]['app_secret'];
        return true;
    }

    public static function getDefaultAppSecretByPlatform(string $platform, string &$app_secret = null): bool {
        $where = SQLWhereOperatorCreator::getInterface(
            SQLColumnsBuilder::getSingleColumn('platform')
        )->create(SQLWhereOperatorCreator::$EQUAL_TO, $platform);
        $where = SQLWhereOperatorCreator::getInterface(
            SQLColumnsBuilder::getSingleColumn('available')
        )->create(SQLWhereOperatorCreator::$EQUAL_TO, 1)->AND($where);
        $columns = SQLColumnsBuilder::getInterface()
            ->add('app_secret')
            ->build();
        $connection = SQLStaticUnit::setup();
        $command = $connection->newSQLReadCommand(
            SQLTableBuilder::buildFormExistTable('sign_keys')
        )->SELECT($columns)->WHERE($where)->buildSQLCommand();
        $result = $connection->readSyn($command);
        if (sizeof($result) == 0){
            return false;
        }
        $app_secret = $result[0]['app_secret'];
        return true;
    }
}