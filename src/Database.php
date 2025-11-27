<?php

namespace App;

use PDO;
use PDOException;

class Database {
    private $host = 'localhost';
    private $db_name = 'h5p_service';
    private $username = 'root'; // Usuario por defecto de XAMPP
    private $password = '';     // Contraseña por defecto de XAMPP (vacía)
    private $charset = 'utf8mb4';
    public $pdo;

    public function __construct() {
        $dsn = "mysql:host=$this->host;dbname=$this->db_name;charset=$this->charset";
        
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION, // Que lance excepciones como C#
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,       // Que devuelva arrays asociativos (clave => valor)
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];

        try {
            $this->pdo = new PDO($dsn, $this->username, $this->password, $options);
        } catch (PDOException $e) {
            // En producción nunca hagas echo del error directamente
            throw new \Exception("Error de conexión a Base de Datos: " . $e->getMessage());
        }
    }

    public function getConnection() {
        return $this->pdo;
    }
}