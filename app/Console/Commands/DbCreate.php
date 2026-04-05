<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use PDO;
use Exception;

class DbCreate extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'db:create {--force : Force creation without checking (useful for CI/CD)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create the database if it doesn\'t exist and then you can run migrations.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $connection = config('database.default');
        
        if ($connection !== 'mysql') {
            $this->info("Database creation via this command is only configured for MySQL. Current: {$connection}");
            return;
        }

        $database = config("database.connections.{$connection}.database");
        $host = config("database.connections.{$connection}.host");
        $port = config("database.connections.{$connection}.port");
        $username = config("database.connections.{$connection}.username");
        $password = config("database.connections.{$connection}.password");
        $charset = config("database.connections.{$connection}.charset", 'utf8mb4');
        $collation = config("database.connections.{$connection}.collation", 'utf8mb4_unicode_ci');

        if (!$database) {
            $this->error("No database is configured in .env for connections.{$connection}.database");
            return;
        }

        try {
            $pdo = new PDO(sprintf('mysql:host=%s;port=%d;', $host, $port), $username, $password);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

            $this->info("Checking if database '{$database}' exists...");
            
            $pdo->exec(sprintf(
                'CREATE DATABASE IF NOT EXISTS `%s` CHARACTER SET %s COLLATE %s;',
                $database,
                $charset,
                $collation
            ));

            $this->info("Database '{$database}' was successfully created or already exists.");

        } catch (Exception $e) {
            $this->error("Failed to create database: " . $e->getMessage());
        }
    }
}
