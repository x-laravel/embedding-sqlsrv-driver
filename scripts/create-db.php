<?php

$host = getenv('DB_HOST') ?: '127.0.0.1';
$port = getenv('DB_PORT') ?: 1433;
$db   = getenv('DB_DATABASE') ?: 'embedding_test';
$user = getenv('DB_USERNAME') ?: 'sa';
$pass = getenv('DB_PASSWORD') ?: '';

$pdo = new PDO("sqlsrv:Server={$host},{$port};TrustServerCertificate=1", $user, $pass);
$pdo->exec("IF NOT EXISTS (SELECT * FROM sys.databases WHERE name = N'{$db}') CREATE DATABASE [{$db}]");
echo "Database '{$db}' ready.\n";
