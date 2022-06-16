<?php
namespace Anton\Tg;

class DB
{
    private $pdo;

    public function __construct() {
        $this->pdo = $this->connect();
    }

    public function getConnection()
    {
        if ($this->pdo == null) {
            return $this->connect();
        }
        return $this->pdo;
    }

    public function createTables() {
        $commands = ['CREATE TABLE IF NOT EXISTS user (
                        id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
                        user_name TEXT NULL,
                        first_name TEXT NULL,
                        tg_user_id INTEGER NOT NULL,
                        tg_update_id INTEGER NOT NULL,
                        banned_time INTEGER NULL
                      )',
            'CREATE TABLE IF NOT EXISTS message (
                    id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
                    message  VARCHAR (255) NOT NULL,
                    tg_update_id  INTEGER NOT NULL,
                    tg_message_id  INTEGER NOT NULL UNIQUE,
                    tg_user_id INTEGER,
                    FOREIGN KEY (tg_user_id)
                    REFERENCES user(tg_user_id) ON UPDATE CASCADE
                                                    ON DELETE CASCADE)'];
        foreach ($commands as $command) {
            $this->pdo->exec($command);
        }
    }



    private function connect() {
        if ($this->pdo == null) {
            $this->pdo = new \PDO('sqlite:mydb.sqlite');
        }
        return $this->pdo;
    }
}