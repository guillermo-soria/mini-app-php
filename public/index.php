<?php
declare(strict_types=1);

use App\Presentation\ComicController;
use App\Infra\XkcdApiClient;
use App\Infra\FavoritesRepository;
use App\Infra\Logger;

require_once __DIR__ . '/../src/Presentation/ComicController.php';
require_once __DIR__ . '/../src/Infra/XkcdApiClient.php';
require_once __DIR__ . '/../src/Infra/FavoritesRepository.php';
require_once __DIR__ . '/../src/Infra/Logger.php';

// Config
$dbPath = __DIR__ . '/../data/favorites.sqlite';
$logPath = __DIR__ . '/../logs/app.log';

// Setup
$pdo = new PDO('sqlite:' . $dbPath);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$logger = new Logger($logPath);
$xkcdService = new XkcdApiClient($logger);
$controller = new ComicController($xkcdService);
$favModel = new FavoritesRepository($pdo);

// Routing
$uri = strtok($_SERVER['REQUEST_URI'], '?');

if ($uri === '/api/favorites') {
    header('Content-Type: application/json; charset=utf-8');
    try {
        $favorites = $favModel->getFavorites();
        http_response_code(200);
        echo json_encode($favorites);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to fetch favorites']);
        $logger->error('Favorites endpoint error: ' . $e->getMessage());
    }
    exit;
}

$num = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$error = null;
if ($num === false && isset($_GET['id'])) {
    $error = 'Invalid comic number. Please enter a valid number.';
    $num = null;
}
$comic = null;
$latestComic = null;

try {
    $latestComic = $controller->show();
    if (!$error) {
        $comic = $controller->show($num);
    }
} catch (Exception $e) {
    $logger->error($e->getMessage());
    $error = $e->getMessage();
}

if ($error) {
    http_response_code(500);
}

$addedFavorite = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['favorite']) && $comic) {
    $date = $comic['year'] . '-' . str_pad($comic['month'], 2, '0', STR_PAD_LEFT) . '-' . str_pad($comic['day'], 2, '0', STR_PAD_LEFT);
    $favData = [
        'num' => $comic['num'],
        'title' => $comic['title'],
        'img' => $comic['img'],
        'alt' => $comic['alt'],
        'date' => $date
    ];
    if ($favModel->addFavorite($favData)) {
        $addedFavorite = true;
    }
}

function esc($str) {
    return htmlspecialchars((string)$str, ENT_QUOTES, 'UTF-8');
}

?><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>XKCD Mini App</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 2em; }
        .comic-img { max-width: 100%; height: auto; }
        .nav-btns { margin: 1em 0; }
        .error { color: red; }
        .success { color: green; }
    </style>
</head>
<body>
    <h1>XKCD Mini App</h1>
    <?php if ($error): ?>
        <div class="error">
            <?php
            if (strpos($error, 'Network error') !== false || strpos($error, 'Could not fetch XKCD comic') !== false) {
                echo 'Network error: Unable to fetch comic. Please check your connection and try again.';
            } elseif (strpos($error, 'Invalid response') !== false || strpos($error, 'comic') !== false) {
                echo 'Comic not found. Please try a different comic number.';
            } else {
                echo 'An unexpected error occurred.';
            }
            ?>
        </div>
        <div><a href="/" class="back-home-btn">Back to Home</a></div>
    <?php elseif ($addedFavorite): ?>
        <div class="success">Comic added to favorites!</div>
        <div><a href="/">Back to Home</a></div>
    <?php elseif ($comic): ?>
        <h2><?= esc($comic['title']) ?> (#<?= esc($comic['num']) ?>)</h2>
        <div><img class="comic-img" src="<?= esc($comic['img']) ?>" alt="<?= esc($comic['alt']) ?>"></div>
        <div><em><?= esc($comic['alt']) ?></em></div>
        <div>Date: <?= esc($comic['year']) ?>-<?= esc($comic['month']) ?>-<?= esc($comic['day']) ?></div>
        <div class="nav-btns">
            <?php
            $maxNum = $latestComic['num'] ?? $comic['num'];
            $currNum = $comic['num'];
            $prev = max(1, $currNum - 1);
            $next = min($maxNum, $currNum + 1);
            $random = random_int(1, $maxNum);
            ?>
            <a href="?id=<?= $prev ?>">Previous</a>
            <a href="?id=<?= $next ?>">Next</a>
            <a href="?id=<?= $random ?>">Random</a>
            <a href="/">Latest</a>
        </div>
        <form method="get" action="/">
            <label for="id">Go to comic #:</label>
            <input type="number" name="id" id="id" min="1" max="<?= esc($maxNum) ?>" value="<?= esc($currNum) ?>">
            <button type="submit">Go</button>
        </form>
        <form method="post" action="?id=<?= esc($currNum) ?>">
            <button type="submit" name="favorite" value="1">Add to Favorites</button>
        </form>
    <?php else: ?>
        <div>No comic to display.</div>
    <?php endif; ?>
    <hr>
    <a href="/api/favorites" target="_blank">View Favorites (JSON)</a>
</body>
</html>
