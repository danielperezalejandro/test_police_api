<?php
class Database {
    private $host = "127.0.0.1:3307"; // cambia a 3306 si usas el puerto por defecto
    private $db_name = "test_police";
    private $username = "root";
    private $password = "";
    public $conn;

    public function connect() {
        $this->conn = null;
        try {
            $this->conn = new PDO(
                "mysql:host={$this->host};dbname={$this->db_name}",
                $this->username,
                $this->password
            );
            $this->conn->exec("set names utf8");
        } catch (PDOException $e) {
            echo json_encode(["error" => "Error de conexión: " . $e->getMessage()]);
        }
        return $this->conn;
    }
}
