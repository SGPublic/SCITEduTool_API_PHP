<?php

namespace sgpublic\scit\tool\sql;

use Exception;
use PDO;

class SQLConnectHelper {
    private $host;
    private $uid;
    private $password;
    private $database;
    private $charset;

    private function __construct(){ }

    public static function getInterface(){
        return new SQLConnectHelper();
    }

    /**
     * 初始化 SQL 链接参数
     *
     * @param string $host 服务器 host
     * @param string $uid 数据库用户名
     * @param string $password 数据库密码
     * @param string $database 要访问的 database
     * @param string $charset 指定字符集，默认为 UTF-8
     * @return SQLConnection 返回 SQL 连接
     */
    public function setup(string $host, string $uid, string $password,
        string $database, string $charset = 'utf8'): SQLConnection {
        $this->host = $host;
        $this->uid = $uid;
        $this->password = $password;
        $this->database = $database;
        $this->charset = $charset;
        return new SQLConnection($this);
    }

    /**
     * 【私有】新建一个 MySQL 连接
     *
     * @throws SQLException 如果连接过程出错则抛出 SQLException
     */
    function getConnection(): PDO {
        if ($this->host != null and $this->uid != null and $this->password != null
            and $this->database != null and $this->charset != null){
            return new PDO('mysql:dbname='.$this->database.';host='.$this->host.';charset='.$this->charset,
                $this->uid, $this->password);
        } else {
            throw new SQLException('The parameters necessary for the link have not been set.');
        }
    }
}

/**
 * SQL 连接状态
 * @package sgpublic\sql
 */
class SQLConnection extends SQLReadAsyCallback {
    private SQLConnectHelper $helper;
    private $callback;

    function __construct(SQLConnectHelper $helper){
        $this->helper = $helper;
    }

    /**
     * 创建一个查询命令
     *
     * @param SQLTable $base_table 指定查询目标数据表
     * @return SQLReadCommandBuilder 返回查询命令辅助构建工具
     */
    public function newSQLReadCommand(SQLTable $base_table): SQLReadCommandBuilder {
        return new SQLReadCommandBuilder($base_table);
    }

    /**
     * 创建一个更新命令
     *
     * @param SQLTable $base_table 指定查询目标数据表
     * @return SQLWriteCommandBuilder 返回更新命令辅助构建工具
     */
    public function newSQLWriteCommand(SQLTable $base_table): SQLWriteCommandBuilder {
        return new SQLWriteCommandBuilder($base_table);
    }

    /**
     * 异步执行写入命令
     *
     * @param int $request_id 请求ID
     * @param SQLCommand $command 待执行的 SQL 命令
     * @param SQLWriteCallback $callback 执行回调
     */
    public function writeAsy(int $request_id, SQLCommand $command, SQLWriteCallback $callback): void {
        try {
            $result = $this->writeSyn($command);
            $callback->onWriteFinish(
                $request_id, strval($result[1]), strval($result[0]), strval($result[2])
            );
        } catch (SQLException $e){
            $callback->onExecuteFailed(
                $request_id, new SQLException($e->getMessage())
            );
        }
    }

    /**
     * 同步执行写入命令
     *
     * @param SQLCommand $command 待执行的 SQL 命令
     * @return array 执行结果状态
     * @throws SQLException 如果执行出错则抛出 SQLException
     */
    public function writeSyn(SQLCommand $command): array {
        $connection = $this->helper->getConnection();
        $statement_sql = $connection->prepare(
            $command->getString()
        );
        $execute = $statement_sql->execute();
        if ($execute == false){
            throw new SQLException(
                'Error executing command: "'.$command->getString().'"',
                $statement_sql->errorCode()
            );
        } else {
            return $statement_sql->errorInfo();
        }
    }

    /**
     * 异步执行查询命令
     *
     * @param int $request_id 请求ID
     * @param SQLCommand $command 待执行的 SQL 命令
     * @param SQLReadCallback $callback 执行回调
     */
    public function readAsy(int $request_id, SQLCommand $command, SQLReadCallback $callback): void {
        try {
            $this->callback = $callback;
            $result = $this->readSyn($command, $this);
            $callback->onReadFinish($request_id, $result);
        } catch (SQLException $e){
            $callback->onQueryFailed(
                $request_id, new SQLException($e->getMessage())
            );
        }
    }

    function onReadRow(array $row, int $index): array {
        return $this->callback->onReadRow(0, $row, $index);
    }

    /**
     * 同步执行查询命令
     *
     * @param SQLCommand $command 待执行的 SQL 命令
     * @param SQLReadAsyCallback|null $callback 查询结果自定义
     * @return array 查询结果
     * @throws SQLException 若查询时出错则抛出 SQLException
     */
    public function readSyn(SQLCommand $command, SQLReadAsyCallback $callback = null): array {
        if ($callback == null){
            $callback = new class extends SQLReadAsyCallback { };
        }
        $connection = $this->helper->getConnection();
        $statement_sql = $connection->prepare(
            $command->getString()
        );
        if ($statement_sql->execute() != false){
            $this_index = 0;
            $result = [];
            $result_sql = $statement_sql->fetchAll();
            foreach ($result_sql as $item) {
                $result[$this_index] = $callback->onReadRow($item, $this_index);
                $this_index++;
            }
            return $result;
        } else {
            throw new SQLException($statement_sql->errorInfo()[2]);
        }
    }
}

/**
 * SQL 更新命令回调
 * @package sgpublic\sql
 */
interface SQLWriteCallback {
    /**
     * 更新命令执行成功时调用此函数
     *
     * @param int $request_id 请求ID
     * @param string $result_code 执行结果代码
     * @param string $error_code 错误代码
     * @param string $error_info 错误信息
     */
    function onWriteFinish(int $request_id, string $result_code, string $error_code = '', string $error_info = ''): void;

    /**
     * 更新命令执行失败时调用此函数
     *
     * @param int $request_id 请求ID
     * @param SQLException $e 错误信息
     */
    function onExecuteFailed(int $request_id, SQLException $e): void;
}

/**
 * SQL 异步查询命令回调
 * @package sgpublic\sql
 */
abstract class SQLReadCallback {
    /**
     * 按行读取查询结果时用户自定义处理
     *
     * @param int $request_id 请求ID
     * @param array $row 原始结果
     * @param int $index 当前所在结果中的行数
     * @return array 返回用户自定义处理后的结果，默认直接返回原始结果
     */
    function onReadRow(int $request_id, array $row, int $index): array {
        return $row;
    }

    /**
     * 当结果处理完成后调用此函数
     *
     * @param int $request_id 请求ID
     * @param array $result 经处理后的结果
     */
    abstract function onReadFinish(int $request_id, array $result): void;

    /**
     * 查询命令执行失败时调用此函数
     *
     * @param int $request_id 请求ID
     * @param SQLException $e 错误信息
     */
    abstract function onQueryFailed(int $request_id, SQLException $e): void;
}

/**
 * SQL 异步查询命令自定义
 * @package sgpublic\sql
 */
abstract class SQLReadAsyCallback {
    /**
     * 按行读取查询结果时用户自定义处理
     *
     * @param array $row 原始结果
     * @param int $index 当前所在结果中的行数
     * @return array 返回用户自定义处理后的结果，默认直接返回原始结果
     */
    function onReadRow(array $row, int $index): array {
        return $row;
    }
}

/**
 * SQL 更新命令辅助构建工具
 * @package sgpublic\sql
 */
class SQLWriteCommandBuilder {
    private string $base_table;

    /**
     * 【私有】构造一个 SQLWriteCommandBuilder 对象
     * @param SQLTable $base_table 指定待操作的数据表
     */
    function __construct(SQLTable $base_table){
        $this->base_table = $base_table->getName();
    }

    /**
     * （INSERT INTO 语句）向数据表中插入新的记录
     *
     * @param SQLInsertAction $action 插入新记录的动作
     * @return SQLCommand 返回命令对象
     */
    public function INSERT_INTO(SQLInsertAction $action): SQLCommand {
        return new SQLCommand('INSERT INTO '.$this->base_table.' '.$action->getString().';');
    }

    /**
     * （UPDATE 语句）更新数据表中已存在的一条记录
     *
     * @param SQLUpdateAction $action 修改记录的动作
     * @return SQLCommand 返回命令对象
     */
    public function UPDATE(SQLUpdateAction $action): SQLCommand {
        return new SQLCommand('UPDATE '.$this->base_table.$action->getString().';');
    }

    /**
     * （DELETE 语句）删除数据表中已存在的一条记录
     *
     * @param SQLDeleteAction $action 删除记录的动作
     * @return SQLCommand 返回命令对象
     */
    public function DELETE(SQLDeleteAction $action): SQLCommand {
        return new SQLCommand('DELETE FROM'.$this->base_table.$action->getString().';');
    }
}

/**
 * SQL 查询命令辅助构建工具
 * @package sgpublic\sql
 */
class SQLReadCommandBuilder {
    private string $base_table;
    private $command;

    /**
     * 【私有】构造一个 SQLReadCommandBuilder 对象
     * @param SQLTable $base_table
     */
    function __construct(SQLTable $base_table){
        $this->base_table = $base_table->getString();
    }

    /**
     * （SELECT 语句）选中列中的所有记录
     *
     * @param SQLColumns $columns 待选中的列的信息
     * @return SQLSelection 返回 Selection，可进行进一步限定或操作
     */
    public function SELECT(SQLColumns $columns): SQLSelection {
        $this->command = 'SELECT ' .$columns->getString()
            ."\n#n#FROM $this->base_table";
        return new SQLSelection($this);
    }

    /**
     * （SELECT DISTINCT 语句）选中列中唯一不同的记录
     *
     * @param SQLColumns $columns 待选中的列的信息
     * @return SQLSelection 返回 Selection，可进行进一步限定或操作
     */
    public function SELECT_DISTINCT(SQLColumns $columns): SQLSelection {
        $this->command = 'SELECT DISTINCT '.$columns->getString()
            ."\n#n#FROM $this->base_table";
        return new SQLSelection($this);
    }

    /**
     * 【私有】返回 SQL 指令
     *
     * @return string
     */
    function getString(): string {
        return $this->command;
    }
}

/**
 * SQL 指令
 * @package sgpublic\sql
 */
class SQLCommand {
    private string $sql_command;

    final function __construct(string $sql_command){
        $this->sql_command = $sql_command;
    }

    /**
     * 【私有】返回 SQL 指令
     *
     * @return string
     */
    final function getString(): string {
        return str_replace('#n#', '', $this->sql_command);
    }

    /**
     * 【私有】返回内部处理的 SQL 指令
     *
     * @return string
     */
    final function getInternalString(): string {
        return $this->sql_command;
    }
}

/**
 * SQL 更新指令
 * @package sgpublic\sql
 */
class SQLWriteCommand extends SQLCommand { }

/**
 * SQL 查询指令
 * @package sgpublic\sql
 */
class SQLReadCommand extends SQLCommand { }

/**
 * SQL 选择状态
 * @package sgpublic\sql
 */
class SQLSelection {
    private string $sql_command;
    private string $where = '';
    private string $order = '';

    public static string $ASC = 'ASC';
    public static string $DESC = 'DESC';

    /**
     * 【私有】构造一个 SQLSelection 对象
     * @param SQLReadCommandBuilder $sql_command
     */
    function __construct(SQLReadCommandBuilder $sql_command){
        $this->sql_command = $sql_command->getString();
    }

    /**
     * （WHERE 语句）限定范围
     *
     * @param SQLWhereOperator $operator 限定条件
     * @return $this 支持链式调用
     */
    public function WHERE(SQLWhereOperator $operator): SQLSelection {
        $this->where = "\n#n#WHERE {$operator->getString()}";
        return $this;
    }

    /**
     * （ORDER BY 语句）设置排序
     *
     * @param string $order_keyword
     * @param SQLColumns $columns
     * @return $this
     * @throws SQLSelectException
     */
    public function ORDER_BY(string $order_keyword, SQLColumns $columns): SQLSelection {
        switch ($order_keyword){
            case self::$DESC:
            case self::$ASC:
                $this->order = "\n#n#ORDER BY {$columns->getString()} $order_keyword";
                break;
            default:
                throw new SQLSelectException('Please use the correct ORDER keyword.');
        }
        return $this;
    }

    /**
     * 构建 SQL 命令
     *
     * @return SQLCommand 返回构建完成的 SQL 命令
     */
    public function buildSQLCommand(): SQLCommand {
        return new SQLCommand("$this->sql_command$this->where$this->order;");
    }
}

/**
 * Insert 动作辅助构建工具
 * @package sgpublic\sql
 */
class SQLInsertActionBuilder {
    private int $insert_method = -1;
    private array $insert_array = [];
    private string $result;

    private function __construct(){ }

    /**
     * 构造 SQLInsertActionBuilder 对象，供外部调用
     *
     * @return SQLInsertActionBuilder
     */
    public static function getInterface(): SQLInsertActionBuilder {
        return new SQLInsertActionBuilder();
    }

    /**
     * 使用列和值一一对应的方式增量插入新记录
     *
     * @param string $column_name 选择待设定值的列
     * @param $value mixed 设定值
     * @return $this 支持链式调用
     * @throws SQLInsertException 若已经采用了全量插入记录的方式，则抛出 SQLInsertException
     * @noinspection PhpMissingBreakStatementInspection
     */
    function addWithColumnName(string $column_name, $value): SQLInsertActionBuilder {
        switch ($this->insert_method){
            case -1:
                $this->insert_method = 0;
            case 0:
                $this->insert_array[$column_name] = str_replace('"', '\"', $value);
                break;
            default:
                throw new SQLInsertException("You have already used the full insertion method, "
                    ."please do not continue to use partial insertion.");
        }
        return $this;
    }

    /**
     * 使用列缺省的方式全量插入新记录
     *
     * @param $value mixed 设定值
     * @return $this 支持链式调用
     * @throws SQLInsertException 若已经采用了增量插入记录的方式，则抛出 SQLInsertException
     * @noinspection PhpMissingBreakStatementInspection
     */
    function addSimplify($value): SQLInsertActionBuilder {
        switch ($this->insert_method){
            case -1:
                $this->insert_method = 1;
            case 1:
                $this->insert_array[] = str_replace('"', '\"', $value);
                break;
            default:
                throw new SQLInsertException("You have already used the partial insertion method, "
                    ."please do not continue to use full insertion.");
        }
        return $this;
    }

    /**
     * 构建 Insert 动作
     *
     * @return SQLInsertAction 返回 Insert 动作对象
     * @throws SQLInsertException 若尚未设置新纪录中的任何值，则抛出 SQLInsertAction
     */
    public function build(): SQLInsertAction {
        switch ($this->insert_method){
            case 0:
                $columns = "\n(";
                $values = "\nVALUES (";
                foreach ($this->insert_array as $column_name => $column_value) {
                    if ($columns != "\n("){
                        $columns = "$columns, ";
                    }
                    $columns = "$columns$column_name";
                    if ($values != "\nVALUES ("){
                        $values = "$values, ";
                    }
                    if (is_string($column_value)){
                        $column_value = "\"$column_value\"";
                    }
                    $values = "$values$column_value";
                }
                $columns = "$columns)";
                $values = "$values)";
                $this->result = $columns.$values;
                return new SQLInsertAction($this);
            case 1:
                $action = "\nVALUES (";
                foreach ($this->insert_array as $item) {
                    if ($action != "\nVALUES ("){
                        $action = "$action, ";
                    }
                    if (is_string($item)){
                        $item = "\"$item\"";
                    }
                    $action = "$action$item";
                }
                $this->result = $action.')';
                return new SQLInsertAction($this);
            default:
                throw new SQLInsertException("You have not added the data to be inserted.");
        }
    }

    /**
     * 【私有】返回 SQL 指令
     *
     * @return string
     */
    function getString(): string {
        return $this->result;
    }
}

/**
 * Insert 动作，用于向数据表中插入一条新记录
 * @package sgpublic\sql
 */
class SQLInsertAction {
    private string $action_string;

    /**
     * 【私有】构造一个 SQLInsertAction 对象
     * @param SQLInsertActionBuilder $action_string
     */
    function __construct(SQLInsertActionBuilder $action_string) {
        $this->action_string = $action_string->getString();
    }

    /**
     * 【私有】返回 SQL 指令
     *
     * @return string
     */
    function getString(): string {
        return $this->action_string;
    }
}

/**
 * Update 动作辅助构建工具
 * @package sgpublic\sql
 */
class SQLUpdateActionBuilder {
    private array $update_array = [];
    private $operator;
    private $result;

    private function __construct(){ }

    /**
     * 构造 SQLUpdateActionBuilder 对象，供外部调用
     *
     * @return SQLUpdateActionBuilder
     */
    public static function getInterface(): SQLUpdateActionBuilder {
        return new SQLUpdateActionBuilder();
    }

    /**
     * （SET 语句）需要更新的列和更新后的值
     *
     * @param string $column_name 需要更新的列
     * @param $value mixed 更新后的值
     * @return $this 支持链式调用
     */
    public function SET(string $column_name, $value): SQLUpdateActionBuilder {
        if (is_bool($value)){
            $value = $value ? 1 : 0;
        }
        $this->update_array[$column_name] = str_replace('"', '\"', $value);
        return $this;
    }

    /**
     * （WHERE 语句）限定待更新记录的范围
     *
     * @param SQLWhereOperator $operator 限定条件
     * @return $this
     */
    public function WHERE(SQLWhereOperator $operator): SQLUpdateActionBuilder {
        $this->operator = $operator;
        return $this;
    }

    /**
     * 构建 Update 动作
     *
     * @param bool $safe_update 是否启用安全更新，默认为真
     * @return SQLUpdateAction 返回构建的 Update 动作
     * @throws SQLUpdateException 若没有设置更新的数据，或启用安全更新的情况下没有设置限定范围，则抛出 SQLUpdateException
     */
    public function build($safe_update = true): SQLUpdateAction {
        $where = '';
        if ($this->operator == null and $safe_update){
            throw new SQLUpdateException('You have turned on safe updates,'
                .'but have not set the "WHERE" statement');
        }
        if (sizeof($this->update_array) == 0){
            throw new SQLUpdateException('You have not set the content to be updated.');
        }
        if ($this->operator != null){
            $where = "\nWHERE {$this->operator->getString()}";
        }

        $set = "\nSET ";
        foreach ($this->update_array as $key => $value){
            if ($set != "\nSET "){
                $set = "$set, ";
            }
            if (is_string($value)){
                $value = "\"$value\"";
            }
            $set = "$set$key=$value";
        }

        $this->result = "$set$where";
        return new SQLUpdateAction($this);
    }

    /**
     * 【私有】返回 SQL 指令
     *
     * @return string
     */
    function getString(): string {
        return $this->result;
    }
}

/**
 * Update 动作，用于在数据表中修改一条记录
 * @package sgpublic\sql
 */
class SQLUpdateAction {
    private string $action_string;

    function __construct(SQLUpdateActionBuilder $action_string) {
        $this->action_string = $action_string->getString();
    }

    /**
     * 【私有】返回 SQL 指令
     *
     * @return string
     */
    function getString(): string {
        return $this->action_string;
    }
}

/**
 * Delete 动作辅助构建工具
 * @package sgpublic\sql
 */
class SQLDeleteActionBuilder {
    private $operator;
    private $result;

    private function __construct(){ }

    /**
     * 构造 SQLDeleteActionBuilder 对象，供外部调用
     *
     * @return SQLDeleteActionBuilder
     */
    public static function getInterface(): SQLDeleteActionBuilder {
        return new SQLDeleteActionBuilder();
    }

    /**
     * （WHERE 语句）限定待删除记录的范围
     *
     * @param SQLWhereOperator $operator 限定条件
     * @return $this 支持链式调用
     */
    public function WHERE(SQLWhereOperator $operator): SQLDeleteActionBuilder {
        $this->operator = $operator;
        return $this;
    }

    /**
     * 构建 Delete 动作
     *
     * @param bool $safe_delete 是否启用安全删除，默认为真
     * @return SQLDeleteAction 返回构建的 Delete 动作
     * @throws SQLDeleteException 若启用安全删除的情况下没有设置限定范围，则抛出 SQLUpdateException
     */
    public function build($safe_delete = true): SQLDeleteAction {
        $where = '';
        if ($this->operator == null and $safe_delete){
            throw new SQLDeleteException('You have turned on safe deletes,'
                .'but have not set the "WHERE" statement');
        }
        if ($this->operator != null){
            $where = "\nWHERE {$this->operator->getString()}";
        }
        $this->result = $where;
        return new SQLDeleteAction($this);
    }

    /**
     * 【私有】返回 SQL 指令
     *
     * @return string
     */
    function getString(): string {
        return $this->result;
    }
}

/**
 * Delete 动作，用于在数据表中删除一条记录
 * @package sgpublic\sql
 */
class SQLDeleteAction {
    private string $action_string;

    function __construct(SQLDeleteActionBuilder $action_string) {
        $this->action_string = $action_string->getString();
    }

    /**
     * 【私有】返回 SQL 指令
     *
     * @return string
     */
    function getString(): string {
        return $this->action_string;
    }
}

/**
 * 数据表列组对象辅助构建工具
 * @package sgpublic\sql
 */
class SQLColumnsBuilder {
    private array $columns = [];

    private function __construct(){ }

    /**
     * 构造 SQLColumnsBuilder 对象，供外部调用
     *
     * @return SQLColumnsBuilder
     */
    public static function getInterface(): SQLColumnsBuilder {
        return new SQLColumnsBuilder();
    }

    /**
     * 在列组对象中添加一个列
     *
     * @param string $column_name 列名称
     * @param string $alias_name 设置别名，留空为不设置
     * @return $this
     */
    public function add(string $column_name, string $alias_name =''): SQLColumnsBuilder {
        $this->columns[$column_name] = $alias_name;
        return $this;
    }

    /**
     * 构建列组对象
     *
     * @return SQLColumns 列组对象
     */
    public function build(): SQLColumns {
        if (sizeof($this->columns) == 0){
            array_push($this->columns, "*");
        }
        return new SQLColumns($this);
    }

    /**
     * 【静态方法】获取一个单列的列组对象
     *
     * @param string $column_name 列名称
     * @return SQLColumns 列组对象
     */
    public static function getSingleColumn(string $column_name): SQLColumns {
        return new SQLColumns(self::getInterface()
            ->add($column_name));
    }

    /**
     * 【静态方法】获取全列（即 *）的列组对象
     *
     * @return SQLColumns 列组对象
     */
    public static function getWholeColumns(): SQLColumns {
        return new SQLColumns(self::getInterface());
    }

    /**
     * 【私有】获取当前对象中的列组
     *
     * @return array 列组
     */
    function getColumns(): array {
        return $this->columns;
    }
}

/**
 * 数据表列组对象
 * @package sgpublic\sql
 */
class SQLColumns {
    private array $columns;

    function __construct(SQLColumnsBuilder $columns){
        $this->columns = $columns->getColumns();
    }

    /**
     * 【私有】返回 SQL 指令
     *
     * @return string
     */
    function getString(): string {
        if ($this->getColumnsCount() == 0){
            $result = "*";
        } else {
            $result = "";
            foreach ($this->columns as $key => $value){
                if ($result != ""){
                    $result = "$result, ";
                }
                $result = "$result$key";
                if ($value != null){
                    $result = "$result AS $value";
                }
            }
        }
        return $result;
    }

    /**
     * 【私有】获取当前列组对象中列的数量
     *
     * @return int 列的数量
     */
    function getColumnsCount(): int {
        return sizeof($this->columns);
    }
}

/**
 * 数据表对象，可以是数据库中已存在的数据表，也可以是一个查询结果
 * @package sgpublic\sql
 */
class SQLTableBuilder {
    private function __construct(){ }

    /**
     * 基于数据库中已存在的数据表构建对象
     *
     * @param string $table_name 数据表名称
     * @return SQLTable
     */
    public static function buildFormExistTable(string $table_name): SQLTable {
        return new SQLTable($table_name);
    }

    /**
     * 基于查询结果构建对象
     *
     * @param string $table_name 临时名称
     * @param SQLReadCommand $command 查询结果
     * @return SQLTable
     */
    public static function buildFormSnapResult(string $table_name, SQLReadCommand $command): SQLTable {
        return new SQLTable($table_name, $command);
    }
}

/**
 * 数据表对象
 * @package sgpublic\sql
 */
class SQLTable {
    private string $table_name;
    private ?SQLCommand $command;

    function __construct(string $table_name, SQLCommand $command = null) {
        $this->table_name = $table_name;
        $this->command = $command;
    }

    /**
     * 获取当前数据表的名称
     *
     * @return string 数据表名称
     */
    public function getName(): string {
        return $this->table_name;
    }

    /**
     * 【私有】返回 SQL 指令
     *
     * @return string
     */
    function getString(): string {
        $result = $this->table_name;
        if ($this->command != null){
            $table = str_replace('#n#', '#n#    ',
                $this->command->getInternalString());
            $table = substr($table, 0, strlen($table) - 1);
            $result = "(\n#n#    $table\n#n#)$this->table_name";
        }
        return $result;
    }

    /**
     * 获取数据表中的子列
     * <br> ___注意！此命令无法检查当前数据表是否存在该子列！___
     *
     * @param string $column_name
     * @param string $alias_name
     * @return SQLColumns
     */
    public function getSubColumn(string $column_name, string $alias_name = ''): SQLColumns {
        $name = "$this->table_name.$column_name";
        if ($alias_name != ''){
            $name = "$name AS $alias_name";
        }
        return SQLColumnsBuilder::getSingleColumn($name);
    }
}

/**
 * WHERE 语句辅助构建工具
 * @package sgpublic\sql
 */
class SQLWhereOperatorCreator {
    private string $column_name;
    private string $result;

    public static string $EQUAL_TO = "=";
    public static string $NOT_EQUAL_TO = "!=";
    public static string $MORE_THAN = ">";
    public static string $MORE_THAN_OR_EQUAL_TO = ">=";
    public static string $LESS_THAN = "<";
    public static string $LESS_THAN_OR_EQUAL_TO = "<=";
    public static string $BETWEEN = "BETWEEN";
    public static string $LIKE = "LIKE";
    public static string $IN = "IN";

    private function __construct(SQLColumns $columns){
        $this->column_name = $columns->getString();
    }

    /**
     * 构造 SQLWhereOperatorCreator 对象，供外部调用
     *
     * @param SQLColumns $columns 列组对象
     * @return SQLWhereOperatorCreator 构造的对象
     * @throws SQLException 若列组对象非单列，则抛出 SQLException
     */
    public static function getInterface(SQLColumns $columns): SQLWhereOperatorCreator {
        if ($columns->getColumnsCount() != 1){
            throw new SQLException('A single "Where" statement only allows filtering'
                .'based on the data in one column.');
        } else {
            return new SQLWhereOperatorCreator($columns);
        }
    }

    /**
     * 创建 SQLWhereOperator 对象
     *
     * @param string $whereOperator WHERE 语句运算符
     * @param mixed ...$value 参数组
     * @return SQLWhereOperator 创建的 SQLWhereOperator 对象
     * @throws SQLWhereOperatorException 若创建的语句不符合语法，则抛出 SQLWhereOperatorException
     */
    public function create(string $whereOperator, ...$value): SQLWhereOperator {
        switch ($whereOperator){
            case self::$EQUAL_TO:
            case self::$NOT_EQUAL_TO:
                if (sizeof($value) == 1){
                    $value_operator = "'$value[0]'";
                } else {
                    throw new SQLWhereOperatorException('The operator like "=" or "!=" '
                        .'only allows a single string.');
                }
                break;
            case self::$MORE_THAN:
            case self::$MORE_THAN_OR_EQUAL_TO:
            case self::$LESS_THAN:
            case self::$LESS_THAN_OR_EQUAL_TO:
                if (is_string($value[0]) or is_array($value[0])){
                    throw new SQLWhereOperatorException('The operator like "<" or ">"'
                        .' only allows numbers.');
                } else {
                    $value_operator = $value[0];
                }
                break;
            case self::$BETWEEN:
                if (sizeof($value) == 2){
                    $value_operator = "$value[0] AND $value[1]";
                } else {
                    throw new SQLWhereOperatorException('The "BETWEEN" operator requires'
                        .' two values to limit the upper and lower limits.');
                }
                break;
            case self::$LIKE:
                if (is_string($value[0]) and sizeof($value) == 1){
                    $value_operator = "'$value[0]'";
                } else {
                    throw new SQLWhereOperatorException('The "LIKE" operator only allows'
                        .' a single string.');
                }
                break;
            case self::$IN:
                $value_operator = '(';
                foreach ($value as $item) {
                    if (is_string($item)){
                        if ($value_operator != '('){
                            $value_operator = "$value_operator,";
                        }
                        $value_operator = "$value_operator'$item'";
                    } else {
                        throw new SQLWhereOperatorException("The \"$value_operator\" "
                            ."operator only allows strings.");
                    }
                }
                $value_operator = "$value_operator)";
                break;
            default:
                throw new SQLWhereOperatorException('Please use the correct "Where"'
                    .' statement operator.');
        }
        $this->result = "$this->column_name $whereOperator $value_operator";
        return new SQLWhereOperator($this);
    }

    /**
     * 【私有】返回 SQL 指令
     *
     * @return string
     */
    function getString(): string {
        return $this->result;
    }
}

class SQLWhereOperator {
    private string $operator;

    function __construct(SQLWhereOperatorCreator $operator){
        $this->operator = $operator->getString();
    }

    /**
     * 【私有】返回 SQL 指令
     *
     * @return string
     */
    function getString(): string {
        return $this->operator;
    }

    /**
     * OR 运算符
     *
     * @param SQLWhereOperator $operator
     * @return $this
     */
    public function OR(SQLWhereOperator $operator): SQLWhereOperator {
        $this->operator = "($this->operator OR {$operator->getString()})";
        return $this;
    }

    /**
     * AND 运算符
     *
     * @param SQLWhereOperator $operator
     * @return $this
     */
    public function AND(SQLWhereOperator $operator): SQLWhereOperator {
        $this->operator = "($this->operator AND {$operator->getString()})";
        return $this;
    }
}

interface ItemEachEvent {
    function onEach($item);
}

interface KeyValueEachEvent {
    function onEach($key, $value);
}

class SQLException extends Exception { }
class SQLSelectException extends SQLException { }
class SQLWhereOperatorException extends SQLException { }
class SQLExecuteException extends SQLException { }
class SQLInsertException extends SQLExecuteException { }
class SQLUpdateException extends SQLExecuteException { }
class SQLDeleteException extends SQLExecuteException { }