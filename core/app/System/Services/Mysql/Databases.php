<?php

namespace App\System\Services\Mysql;

use Illuminate\Database\Connection;
use PDO;

class Databases
{
    public function __construct(
        protected PDO $pdo,
    ) {
    }

    public function databaseExists(string $name): bool
    {
        $conn = new Connection($this->pdo);
        $query = "SHOW DATABASES LIKE ?";
        $result = $conn->select($query, [$name]);
        return !empty($result);
    }

    public function createDatabase(string $name): void
    {
        $query = "CREATE DATABASE `{$name}`";

        $this->pdo->exec($query);
    }

    public function deleteDatabase(string $name): void
    {
        $query = "DROP DATABASE `{$name}`";
        $this->pdo->exec($query);
    }

    public function getDatabaseSize(string $name): int
    {
        $conn = new Connection($this->pdo);
        $query = "SELECT SUM(data_length + index_length) AS size FROM information_schema.tables WHERE table_schema = ?";
        $result = $conn->select($query, [$name]);
        $firstKey = array_key_first($result);
        if (
            $firstKey
            && is_object($result[$firstKey])
            && (is_string($result[$firstKey]->size) || is_int($result[$firstKey]->size))
        ) {
            return (int)$result[$firstKey]->size;
        }
        return 0;
    }
}
