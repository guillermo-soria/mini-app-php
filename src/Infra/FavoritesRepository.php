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
        // Set busy timeout to mitigate 'database is locked' errors on SQLite
        $this->pdo->exec('PRAGMA busy_timeout = 5000');
        $this->initTable();
    }

    /**
     * Execute SQL with simple retry on 'database is locked'.
     */
    private function execWithRetry(string $sql): void
    {
        $tries = 0;
        while (true) {
            try {
                $this->pdo->exec($sql);
                return;
            } catch (\PDOException $e) {
                $msg = $e->getMessage();
                if (stripos($msg, 'database is locked') !== false && $tries < 5) {
                    $tries++;
                    usleep(200000); // 200ms
                    continue;
                }
                throw $e;
            }
        }
    }

    private function initTable(): void
    {
        // Check if table exists
        $stmt = $this->pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name='favorites'");
        $exists = $stmt && $stmt->fetchColumn() !== false;
        if (!$exists) {
            $this->execWithRetry("CREATE TABLE IF NOT EXISTS favorites (
                comic_id INTEGER PRIMARY KEY,
                title TEXT,
                img TEXT,
                alt TEXT,
                original_date TEXT,
                created_at TEXT,
                UNIQUE(comic_id)
            )");
            return;
        }

        // Inspect existing columns
        $colsStmt = $this->pdo->query("PRAGMA table_info(favorites)");
        $cols = [];
        if ($colsStmt) {
            $rows = $colsStmt->fetchAll(PDO::FETCH_ASSOC);
            foreach ($rows as $r) {
                $cols[] = $r['name'];
            }
        }

        // If desired column exists, ensure full set of columns present, add missing ones
        if (in_array('comic_id', $cols, true)) {
            $required = ['title', 'img', 'alt', 'original_date', 'created_at'];
            foreach ($required as $col) {
                if (!in_array($col, $cols, true)) {
                    $this->execWithRetry("ALTER TABLE favorites ADD COLUMN $col TEXT");
                }
            }
            return;
        }

        // If old schema uses comic_num, migrate to new schema
        if (in_array('comic_num', $cols, true)) {
            $this->pdo->beginTransaction();
            try {
                // create new table with full schema
                $this->execWithRetry("CREATE TABLE IF NOT EXISTS favorites_new (
                    comic_id INTEGER PRIMARY KEY,
                    title TEXT,
                    img TEXT,
                    alt TEXT,
                    original_date TEXT,
                    created_at TEXT,
                    UNIQUE(comic_id)
                )");
                // Determine original_date source (date or NULL)
                $origDateCol = in_array('date', $cols, true) ? 'date' : 'NULL';
                // Copy data (img/alt may not exist in old schema)
                $this->execWithRetry("INSERT OR IGNORE INTO favorites_new (comic_id, title, img, alt, original_date, created_at) SELECT comic_num, title, COALESCE(img, ''), COALESCE(alt, ''), " . $origDateCol . ", datetime('now') FROM favorites");
                // Drop old and rename
                $this->execWithRetry("DROP TABLE favorites");
                $this->execWithRetry("ALTER TABLE favorites_new RENAME TO favorites");
                $this->pdo->commit();
            } catch (\PDOException $e) {
                $this->pdo->rollBack();
                throw $e;
            }
            return;
        }

        // Fallback: ensure table exists with desired schema
        $this->execWithRetry("CREATE TABLE IF NOT EXISTS favorites (
            comic_id INTEGER PRIMARY KEY,
            title TEXT,
            img TEXT,
            alt TEXT,
            original_date TEXT,
            created_at TEXT,
            UNIQUE(comic_id)
        )");
    }

    public function addFavorite(array $comic): bool
    {
        // Validate required keys
        foreach (['num', 'title'] as $key) {
            if (!array_key_exists($key, $comic)) {
                throw new \InvalidArgumentException("Missing required comic key: $key");
            }
        }
        $img = $comic['img'] ?? '';
        $alt = $comic['alt'] ?? '';
        // original date from comic (year-month-day) if present
        if (isset($comic['year'], $comic['month'], $comic['day'])) {
            $originalDate = sprintf('%04d-%02d-%02d', (int)$comic['year'], (int)$comic['month'], (int)$comic['day']);
        } elseif (!empty($comic['date'])) {
            $originalDate = (string)$comic['date'];
        } else {
            $originalDate = null;
        }
        $stmt = $this->pdo->prepare("INSERT OR IGNORE INTO favorites (comic_id, title, img, alt, original_date, created_at) VALUES (?, ?, ?, ?, ?, ?)");
        return $stmt->execute([
            $comic['num'],
            $comic['title'],
            $img,
            $alt,
            $originalDate,
            date('Y-m-d H:i:s')
        ]);
    }

    public function getFavorites(): array
    {
        $stmt = $this->pdo->query("SELECT comic_id, title, img, alt, original_date, created_at FROM favorites");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
