<?php
date_default_timezone_set('Asia/Jakarta');

class Database
{
    // private $host = "localhost";
    // private $db_name = "attendance_db";
    // private $username = "root";
    // private $password = "";

    private $host = "localhost";
    private $db_name = "attendance_db";
    private $username = "andi";
    private $password = "passwordbaru";
    public $conn;

    public function getConnection()
    {
        $this->conn = null;

        $this->conn = new PDO("mysql:host=" . $this->host . ";dbname=" . $this->db_name, $this->username, $this->password);
        $this->conn->exec("set names utf8");
        $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->conn->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

        return $this->conn;
    }
}
