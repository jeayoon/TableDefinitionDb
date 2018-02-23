<?php
namespace App\Statics;

use Cake\Datasource\ConnectionManager;


/**
 * DbStatic
 */
class DbStatic
{
    /**
     * getTableList
     *
     * @return array
     */
    public static function getTableList()
    {
        return ConnectionManager::get('default')->schemaCollection()->listTables();
    }

    /**
     * accessConnectionManager
     *
     * @return \Cake\Datasource\ConnectionInterface
     */
    public static function accessConnectionManager()
    {
        return ConnectionManager::get('default');
    }
}
