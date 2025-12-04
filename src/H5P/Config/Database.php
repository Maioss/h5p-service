<?php
declare(strict_types=1);

namespace App\H5P;

use PDO;
use PDOException;


class Database
{
    private PDO $pdo;

    public function __construct(array $dbConfig)
    {
        $this->pdo = new PDO(
            $dbConfig['dsn'],
            $dbConfig['user'],
            $dbConfig['password'],
            $dbConfig['options'] ?? []
        );
    }

    public function getConnection(): PDO
    {
        return $this->pdo;
    }
      /**
     * Simple helper para verificar conexión (opcional)
     */
    public function ping(): bool
    {
        try {
            $stmt = $this->pdo->query('SELECT 1');
            $stmt->fetchColumn();
            return true;
        } catch (PDOException $e) {
            return false;
        }
    }
}
