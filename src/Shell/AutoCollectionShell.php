<?php
namespace AutoCollectionOfDB\Shell;

use App\Defines\DbDefine;
use App\Statics\DbStatic;
use Cake\Console\Shell;
use Cake\Datasource\ConnectionManager;
use Liberty\Liberty;
use Symfony\Component\Yaml\Yaml;

class AutoCollectionShell extends Shell
{
    public function main()
    {
        //getDatabaseType And DatabaseName
        $datasource = ConnectionManager::get('default')->config();
        //getDatabaseType
        $ex = explode("\\", $datasource['driver']);
        $dbType = $ex[3];

        //getDBName
        //Mergeした時の注意、DBネームを取得するこの書き方が正しい。
        $myDatabasesName = $datasource['database'];

        //Mysqlの場合
        if ($dbType == DbDefine::MYSQL) {

            $this->extractMysql($dbType, $myDatabasesName);

            //Postgresの場合
        } elseif ($dbType == DbDefine::POSTGRES) {

            $this->extractPostgres($dbType, $myDatabasesName);

        } else {
            echo "このShellはMysqlとPostgresしか使えません", PHP_EOL;
        }
    }

    /**
     * extractMysql
     *
     * @param $dbType
     * @param $myDatabasesName
     */
    private function extractMysql($dbType, $myDatabasesName)
    {
        //getTableList
        $myTables = DbStatic::getTableList();

        $db = DbStatic::accessConnectionManager();

        $thisMySqlTable = [];
        //mysqlTable取得
        foreach ($myTables as $myTableInd => $myTable){
            //migrationで作られたら、phinxlogテーブルが存在するので、continueで対応。
            if ($myTable === 'phinxlog') {
                continue;
            }
            //getColumnsList
            $myColumnsList = Liberty::sql('AutoCollectionOfDB.columns_list', ['tableName' => $myTable]);
            $myColumnsList = $db->execute($myColumnsList)->fetchAll('assoc');

            $thisMySqlColumns = [];
            foreach ($myColumnsList as $myColumnInd => $myColumnsListValue){
                //TABLE_SCHEMAが$myDatabasesNameと間違った場合、取得しない
                if($myColumnsListValue['TABLE_SCHEMA'] !== $myDatabasesName){
                    continue;
                }

                if ((preg_match("/^([^(]+)\(([0-9]+)\)$/", $myColumnsListValue['COLUMN_TYPE'], $data_type) === 1) ||
                    (preg_match("/^([^(]+)\(([0-9]+,[0-9]+)\)$/",$myColumnsListValue['COLUMN_TYPE'], $data_type) === 1)){
                    //DECIMALの場合DECIMAL(10,0)で表すので、preg_match("/^([^(]+)\(([0-9]+,[0-9]+)\)$/"の対応も必要
                    //data_type
                    //mysqlのデータタイプは大文字
                    $columnsType = strtoupper($data_type[1]);
                    //positional_number
                    $columnPN = $data_type[2];
                }else{
                    //data_type
                    //mysqlのデータタイプは大文字
                    $columnsType = strtoupper($myColumnsListValue['COLUMN_TYPE']);
                    //positional_number
                    $columnPN = "";
                }

                //default_value
                $columnDefault = $myColumnsListValue['COLUMN_DEFAULT'];
                //timestampのタイプは勝手に表すので、ひとまず空っぽにして後で対応する
                if ($myColumnsListValue['COLUMN_TYPE'] == 'timestamp'){
                    $columnDefault = "";
                }

                //logic_name
                $columnLN = $myColumnsListValue['COLUMN_COMMENT'];

                //primary
                $columnPri = "false";
                if ($myColumnsListValue['COLUMN_KEY'] == 'PRI'){
                    $columnPri = "true";
                    //mysqlのprimaryKeyはINTEGER NOT NULL AUTO_INCREMENTで表す
                    $columnsType = "INTEGER NOT NULL AUTO_INCREMENT";
                }

                //not_null
                $columnNn = "true";
                if ($myColumnsListValue['IS_NULLABLE'] == 'YES'){
                    $columnNn = "false";
                }

                //unique
                $columnUni = "false";
                if ($myColumnsListValue['COLUMN_KEY'] == 'UNI'){
                    $columnUni = "true";
                }

                //index
                $columnInd = "false";
                if ($myColumnsListValue['COLUMN_KEY'] == 'MUL'){
                    $columnInd = "true";
                }

                $thisMySqlColumns[$myColumnInd] = [
                    'name' => $myColumnsListValue['COLUMN_NAME'],
                    'data_type' => $columnsType,
                    'positional_number' => $columnPN,
                    'default_value' => $columnDefault,
                    'logic_name' => $columnLN,
                    'primary' => $columnPri,
                    'not_null' => $columnNn,
                    'unique' => $columnUni,
                    'index' => $columnInd];
            }

            $thisMySqlTable[$myTableInd] = ['name' => $myTable,
                'columns' => $thisMySqlColumns];
        }

        $mySqlInformation = ['databases' =>
            ['name' => $myDatabasesName,
                'database_type' => strtolower($dbType),
                'tables' => $thisMySqlTable]];
        $writePgSql = Yaml::dump($mySqlInformation);
        file_put_contents(ROOT . DS . 'vendor/jeayoon/AutoCollectionOfDB/dbData.yml', $writePgSql);
    }


    /**
     * extractPostgres
     *
     * @param $dbType
     * @param $myDatabasesName
     */
    private function extractPostgres($dbType, $myDatabasesName)
    {
        //getTableList
        $pgTables = DbStatic::getTableList();

        $db = DbStatic::accessConnectionManager();

        $thisPgSqlTable = [];
        //tableの情報を書き込む
        foreach ($pgTables as $pgTableInd => $pgTable) {
            //migrationsでテーブルを作って、DBからテーブル情報を持って来た際、いらない「phinxlog」テーブルがあり、continueで対応するもの
            if ($pgTable === 'phinxlog') {
                continue;
            }
            //getColumnsList
            $pgColumnsList = Liberty::sql('AutoCollectionOfDB.columns_list', ['tableName' => $pgTable]);
            $pgColumnsList = $db->execute($pgColumnsList)->fetchAll('assoc');

            //getColumnsLogicalName
            $pgColumnsLogicalName = Liberty::sql('AutoCollectionOfDB.columns_logical_name_for_pgsql', ['tableName' => $pgTable]);
            $pgColumnsLogicalName = $db->execute($pgColumnsLogicalName)->fetchAll('assoc');

            //getColumnsIndex
            $pgColumnsIndex = Liberty::sql('AutoCollectionOfDB.columns_index_key_for_pgsql', ['tableName' => $pgTable]);
            $pgColumnsIndex = $db->execute($pgColumnsIndex)->fetchAll('assoc');

            $thisPgSqlColumns = [];
            foreach ($pgColumnsList as $pgColumnsInd => $pgColumnsListValue) {
                //Postgresの場合、カラムのリストを持ってくる時、table_schemaがinformation_schemaの場合、
                //開発で作ったカラム以外のものが取得されるので、分岐処理を作成
                if ($pgColumnsListValue['table_schema'] == 'information_schema') {
                    continue;
                }

                //data_type
                $columnsType = $pgColumnsListValue['data_type'];
                //Postgresの場合、日付に関するデータタイプだったら、「timestamp without time zone」で表すので、
                //timestampが入っているデータタイプの場合、「timestamp」の値で書き込まれるように処理
                if (strpos($pgColumnsListValue['data_type'], 'timestamp') !== false) {
                    $columnsType = "timestamp";
                    //Postgresの場合、characterデータタイプだったら、「character varying」で表すので、
                    //characterが入っているデータタイプの場合、「character」の値で書き込まれるように処理
                } elseif (strpos($pgColumnsListValue['data_type'], 'character') !== false) {
                    $columnsType = "character";
                }

                //positional_number
                $columnPN = $pgColumnsListValue['character_maximum_length'];
                //Postgresの場合、桁数がヌルだったら、値が何も表示されないので、ヌルの場合Stringタイプのnullを別に書き込む
                if (is_null($pgColumnsListValue['character_maximum_length'])) {
                    $columnPN = "";
                }

                //default_value
                //Postgresの場合、PrimaryKeyの初期値が「nextval('columns_id_seq'::regclass)」だと表すので、
                //「nextval」の文字が入っていたら、Stringタイプの「nextval」を書き込む
                if (strpos($pgColumnsListValue['column_default'], 'nextval') !== false) {
                    $columnDefault = "nextval";
                } else {
                    $columnDefault = $pgColumnsListValue['column_default'];
                    //Postgresの場合、桁数がヌルだったら、値が何も表示されないので、ヌルの場合Stringタイプのnullを別に書き込む
                    if (is_null($pgColumnsListValue['column_default'])) {
                        $columnDefault = "";
                    }
                }

                //logical_name
                $columnLN = "";
                //Postgresの場合、カラムリストのsqlとカラム論理ネームのsqlが違うので、
                //カラムリストにあるカラムの物理名がカラム論理ネームにある場合、
                //変数に書き込まれるように作成
                foreach ($pgColumnsLogicalName as $pgColumnsLogicalNameValue) {
                    if ($pgColumnsLogicalNameValue['column_name'] == $pgColumnsListValue['column_name']) {
                        $columnLN = $pgColumnsLogicalNameValue['column_comment'];
                    }
                }

                //primary_key
                $columnPri = "false";
                if (strpos($pgColumnsListValue['column_default'], 'nextval') !== false) {
                    $columnPri = "true";
                    //postgresのprimaryKeyはserialで表す
                    $columnsType = "serial";
                }


                //Not_null
                $columnNn = "true";
                if ($pgColumnsListValue['is_nullable'] == 'YES') {//is_nullableがYesだったら、null許可
                    $columnNn = "false";
                }

                //unique_key
                $columnUni = "false";
                foreach ($pgColumnsIndex as $pgColumnsIndexValue) {
                    if (($pgColumnsIndexValue['column_name'] == $pgColumnsListValue['column_name'])
                        && ($pgColumnsIndexValue['uniqueness'] == true)
                        && (strpos($pgColumnsIndexValue['index_name'], 'pkey') === false)
                    ) {
                        //$pgColumnsIndexのindex_nameに「テーブル名_pkey」が入っていたらPRIでみなされる。
                        //テーブル定義書にはUNI,INDはチャックしないので、PRIが通らないように作成
                        $columnUni = "true";
                    }
                }

                //index_key
                $columnInd = "false";
                foreach ($pgColumnsIndex as $pgColumnsIndexValue) {
                    //$pgColumnsIndexのindex_nameに「テーブル名_pkey」が入っていたらPRIでみなされる。
                    //テーブル定義書にはUNI,INDはチャックしないので、PRIが通らないように作成
                    if (($pgColumnsIndexValue['column_name'] == $pgColumnsListValue['column_name'])
                        && (strpos($pgColumnsIndexValue['index_name'], 'pkey') === false)
                    ) {
                        $columnInd = "true";
                    }
                }

                $thisPgSqlColumns[$pgColumnsInd] = [
                    'name' => $pgColumnsListValue['column_name'],
                    'data_type' => $columnsType,
                    'positional_number' => $columnPN,
                    'default_value' => $columnDefault,
                    'logic_name' => $columnLN,
                    'primary' => $columnPri,
                    'not_null' => $columnNn,
                    'unique' => $columnUni,
                    'index' => $columnInd];
            }

            $thisPgSqlTable[$pgTableInd] = ['name' => $pgTable,
                'columns' => $thisPgSqlColumns];
        }

        $pgSqlInformation = ['databases' =>
            ['name' => $myDatabasesName,
                'database_type' => strtolower($dbType),
                'tables' => $thisPgSqlTable]];
        $writePgSql = Yaml::dump($pgSqlInformation);
        file_put_contents(ROOT . DS . 'vendor/jeayoon/AutoCollectionOfDB/dbData.yml', $writePgSql);

    }
}
