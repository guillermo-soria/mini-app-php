<?php
namespace App\Infra;

use PDO;
use PDOException;

class FavoritesRepository
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
        $this->initTable();
    }

    private function initTable(): void
    {
        $this->pdo->exec("CREATE TABLE IF NOT EXISTS favorites (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            comic_num INTEGER UNIQUE NOT NULL,
            title TEXT,
            img TEXT,
            alt TEXT,
            date TEXT
        )");
    }

    public function addFavorite(array $comic): bool
    {
        $stmt = $this->pdo->prepare("INSERT OR IGNORE INTO favorites (comic_num, title, img, alt, date) VALUES (?, ?, ?, ?, ?)");
        return $stmt->execute([
            $comic['num'],
            $comic['title'],
            $comic['img'],
            $comic['alt'],
            $comic['date']
        ]);
    }

    public function getFavorites(): array
    {
        $stmt = $this->pdo->query("SELECT comic_num, title, img, alt, date FROM favorites");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

