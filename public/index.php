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
$dbPath = getenv('FAVORITES_DB') ?: (__DIR__ . '/../data/favorites.sqlite');
$logPath = __DIR__ . '/../logs/app.log';

// Ensure the directory for the database exists
$dbDir = dirname($dbPath);
if (!is_dir($dbDir)) {
    mkdir($dbDir, 0775, true);
}

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
        // Map DB fields to API fields: num, title, img, alt, date
        $out = array_map(function($r) {
            return [
                'num' => isset($r['comic_id']) ? (int)$r['comic_id'] : null,
                'title' => $r['title'] ?? null,
                'img' => $r['img'] ?? null,
                'alt' => $r['alt'] ?? null,
                'date' => $r['original_date'] ?? $r['created_at'] ?? null,
            ];
        }, $favorites);
        http_response_code(200);
        echo json_encode($out);
    } catch (Exception $e) {
        http_response_code(500);
        $traceId = bin2hex(random_bytes(8));
        echo json_encode([
            'error' => 'Failed to fetch favorites',
            'trace_id' => $traceId
        ]);
        $logger->error('Favorites endpoint error: ' . $e->getMessage() . ' [trace_id: ' . $traceId . ']');
    }
    exit;
}

$num = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$error = null;
$comic = null;
$latestComic = null;

try {
    $latestComic = $controller->show();
    $maxNum = $latestComic['num'] ?? 1;
    // Clamp $num to [1, $maxNum] if set
    if ($num !== null) {
        if ($num < 1) $num = 1;
        if ($num > $maxNum) $num = $maxNum;
    }
    if ($num === false && isset($_GET['id'])) {
        $error = 'Invalid comic number. Please enter a valid number.';
        $num = null;
    }
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
    // Validate year, month, and day
    $year = isset($comic['year']) ? filter_var($comic['year'], FILTER_VALIDATE_INT) : false;
    $month = isset($comic['month']) ? filter_var($comic['month'], FILTER_VALIDATE_INT) : false;
    $day = isset($comic['day']) ? filter_var($comic['day'], FILTER_VALIDATE_INT) : false;
    if ($year !== false && $month !== false && $day !== false &&
        $year > 0 && $month >= 1 && $month <= 12 && $day >= 1 && $day <= 31) {
        $date = sprintf('%04d-%02d-%02d', $year, $month, $day);
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
    } else {
        $logger->error('Invalid date fields for favorite: ' . json_encode([$comic['year'], $comic['month'], $comic['day']]));
    }
}

function esc($str) {
    return htmlspecialchars((string)$str, ENT_QUOTES, 'UTF-8');
}

// If this is an HTMX request, return only the main fragment (so htmx can swap it)
if (!empty($_SERVER['HTTP_HX_REQUEST'])) {
    // Render main fragment
    // Note: keep output consistent with the main <main id="main"> ... </main> content below
    if ($error) {
        echo '<div class="mb-4 p-4 bg-red-100 text-red-800 rounded">';
        if (strpos($error, 'Network error') !== false || strpos($error, 'Could not fetch XKCD comic') !== false) {
            echo 'Network error: Unable to fetch comic. Please check your connection and try again.';
        } elseif (strpos($error, 'Invalid response') !== false || strpos($error, 'comic') !== false) {
            echo 'Comic not found. Please try a different comic number.';
        } else {
            echo 'An unexpected error occurred.';
        }
        echo '</div>';
        echo '<div class="mb-6"><a href="/" class="text-blue-600 hover:underline">Back to Home</a></div>';
    } elseif ($addedFavorite) {
        echo '<div class="mb-4 p-4 bg-green-100 text-green-800 rounded">Comic added to favorites!</div>';
        echo '<div class="mb-6"><a href="/" class="text-blue-600 hover:underline">Back to Home</a></div>';
    } elseif ($comic) {
        ob_start();
        ?>
        <section id="comic" class="bg-white shadow rounded p-6">
            <h2 class="text-xl font-medium mb-2"><?= esc($comic['title']) ?> <span class="text-sm text-gray-500">#<?= esc($comic['num']) ?></span></h2>
            <div class="mb-4">
                <img class="w-full h-auto rounded" src="<?= esc($comic['img']) ?>" alt="<?= esc($comic['alt']) ?>">
            </div>
            <div class="mb-4 text-gray-700"><em><?= esc($comic['alt']) ?></em></div>
            <div class="mb-4 text-sm text-gray-500">Date: <?= esc($comic['year']) ?>-<?= esc($comic['month']) ?>-<?= esc($comic['day']) ?></div>

            <div class="flex items-center gap-3 mb-4">
                <?php
                $maxNum = $latestComic['num'] ?? ($comic['num'] ?? 1);
                $currNum = $comic['num'] ?? 1;
                $prev = max(1, $currNum - 1);
                $next = min($maxNum, $currNum + 1);
                $random = random_int(1, $maxNum);
                ?>
                <a href="?id=<?= $prev ?>" hx-get="?id=<?= $prev ?>" hx-target="#main" hx-swap="innerHTML" role="button" class="px-3 py-1 bg-gray-200 rounded hover:bg-gray-300 cursor-pointer">Previous</a>
                <a href="?id=<?= $next ?>" hx-get="?id=<?= $next ?>" hx-target="#main" hx-swap="innerHTML" role="button" class="px-3 py-1 bg-gray-200 rounded hover:bg-gray-300 cursor-pointer">Next</a>
                <a href="?id=<?= $random ?>" hx-get="?id=<?= $random ?>" hx-target="#main" hx-swap="innerHTML" role="button" class="px-3 py-1 bg-gray-200 rounded hover:bg-gray-300 cursor-pointer">Random</a>
                <a href="/" class="px-3 py-1 bg-blue-600 text-white rounded hover:bg-blue-700">Latest</a>
            </div>

            <form method="get" action="/" class="mb-4 flex items-center gap-2">
                <label for="id" class="text-sm">Go to comic #:</label>
                <input type="number" name="id" id="id" min="1" max="<?= esc($maxNum) ?>" value="<?= esc($currNum) ?>" class="border rounded px-2 py-1 w-28">
                <button type="submit" class="px-3 py-1 bg-indigo-600 text-white rounded">Go</button>
            </form>

            <form method="post" action="?id=<?= esc($currNum) ?>" class="inline">
                <button type="submit" name="favorite" value="1" class="px-3 py-1 bg-yellow-400 rounded">Add to Favorites</button>
            </form>
        </section>
        <?php
        $fragment = ob_get_clean();
        echo $fragment;
    } else {
        echo '<div class="text-gray-600">No comic to display.</div>';
    }
    exit;
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>XKCD Mini App</title>
    <!-- Tailwind CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- HTMX -->
    <script src="https://unpkg.com/htmx.org@1.9.2"></script>
    <!-- Alpine.js -->
    <script src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
</head>
<body class="bg-gray-50 text-gray-900">
<div class="max-w-3xl mx-auto p-6" id="app" x-data>
    <header class="mb-6">
        <h1 class="text-3xl font-semibold">XKCD Mini App</h1>
    </header>

    <main id="main">
    <?php if ($error): ?>
        <div class="mb-4 p-4 bg-red-100 text-red-800 rounded">
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
        <div class="mb-6"><a href="/" class="text-blue-600 hover:underline">Back to Home</a></div>
    <?php elseif ($addedFavorite): ?>
        <div class="mb-4 p-4 bg-green-100 text-green-800 rounded">Comic added to favorites!</div>
        <div class="mb-6"><a href="/" class="text-blue-600 hover:underline">Back to Home</a></div>
    <?php elseif ($comic): ?>
        <section id="comic" class="bg-white shadow rounded p-6">
            <h2 class="text-xl font-medium mb-2"><?= esc($comic['title']) ?> <span class="text-sm text-gray-500">#<?= esc($comic['num']) ?></span></h2>
            <div class="mb-4">
                <img class="w-full h-auto rounded" src="<?= esc($comic['img']) ?>" alt="<?= esc($comic['alt']) ?>">
            </div>
            <div class="mb-4 text-gray-700"><em><?= esc($comic['alt']) ?></em></div>
            <div class="mb-4 text-sm text-gray-500">Date: <?= esc($comic['year']) ?>-<?= esc($comic['month']) ?>-<?= esc($comic['day']) ?></div>

            <div class="flex items-center gap-3 mb-4">
                <?php
                $maxNum = $latestComic['num'] ?? ($comic['num'] ?? 1);
                $currNum = $comic['num'] ?? 1;
                $prev = max(1, $currNum - 1);
                $next = min($maxNum, $currNum + 1);
                $random = random_int(1, $maxNum);
                ?>
                <a href="?id=<?= $prev ?>" hx-get="?id=<?= $prev ?>" hx-target="#main" hx-swap="innerHTML" role="button" class="px-3 py-1 bg-gray-200 rounded hover:bg-gray-300 cursor-pointer">Previous</a>
                <a href="?id=<?= $next ?>" hx-get="?id=<?= $next ?>" hx-target="#main" hx-swap="innerHTML" role="button" class="px-3 py-1 bg-gray-200 rounded hover:bg-gray-300 cursor-pointer">Next</a>
                <a href="?id=<?= $random ?>" hx-get="?id=<?= $random ?>" hx-target="#main" hx-swap="innerHTML" role="button" class="px-3 py-1 bg-gray-200 rounded hover:bg-gray-300 cursor-pointer">Random</a>
                <a href="/" class="px-3 py-1 bg-blue-600 text-white rounded hover:bg-blue-700">Latest</a>
            </div>

            <form method="get" action="/" class="mb-4 flex items-center gap-2">
                <label for="id" class="text-sm">Go to comic #:</label>
                <input type="number" name="id" id="id" min="1" max="<?= esc($maxNum) ?>" value="<?= esc($currNum) ?>" class="border rounded px-2 py-1 w-28">
                <button type="submit" class="px-3 py-1 bg-indigo-600 text-white rounded">Go</button>
            </form>

            <form method="post" action="?id=<?= esc($currNum) ?>" class="inline">
                <button type="submit" name="favorite" value="1" class="px-3 py-1 bg-yellow-400 rounded">Add to Favorites</button>
            </form>
        </section>
    <?php else: ?>
        <div class="text-gray-600">No comic to display.</div>
    <?php endif; ?>
    </main>

    <footer class="mt-6 text-sm text-gray-500">
        <a href="/api/favorites" class="text-blue-600 hover:underline" target="_blank">View Favorites (JSON)</a>
    </footer>
</div>
</body>
</html>
