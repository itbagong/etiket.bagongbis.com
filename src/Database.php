<?php

class Database {

    private static $instance = null;

    private static $host = 'www.bagongbis.com';
    private static $user = 'bagongbi_bagongtiket';
    private static $password = 'cvbagong.1994';
    private static $db = 'bagongbi_e-tiket';
    
    public static function getInstance(){
        if(self::$instance == null){
            self::$instance = new mysqli(self::$host, self::$user, self::$password, self::$db);
        }
        return self::$instance;
    } 
}
?>