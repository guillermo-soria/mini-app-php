<?php
// Expects: $error, $addedFavorite, $comic, $latestComic, esc()


// Ensure expected variables exist and have safe defaults
$error = $error ?? null;
$addedFavorite = $addedFavorite ?? false;
$comic = $comic ?? null;
$latestComic = $latestComic ?? null;

// Ensure esc() is defined
if (!function_exists('esc')) {
    function esc($s)
    {
        return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

// Guarantee maxNum >= 1 before using random_int later
$maxNum = $latestComic['num'] ?? ($comic['num'] ?? 1);
$maxNum = max(1, (int)$maxNum);
if ($error): ?>
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
    <div class="mb-6"><button type="button" hx-get="/" hx-target="#main" hx-swap="innerHTML" class="px-3 py-1 bg-gray-200 rounded hover:bg-gray-300 cursor-pointer">Back to Home</button></div>
<?php elseif ($addedFavorite): ?>
    <div class="mb-4 p-4 bg-green-100 text-green-800 rounded">Comic added to favorites!</div>
    <div class="mb-6"><button type="button" hx-get="/" hx-target="#main" hx-swap="innerHTML" class="px-3 py-1 bg-gray-200 rounded hover:bg-gray-300 cursor-pointer">Back to Home</button></div>
<?php elseif ($comic):
    $maxNum = $latestComic['num'] ?? ($comic['num'] ?? 1);
    $currNum = $comic['num'] ?? 1;
    $prev = max(1, $currNum - 1);
    $next = min($maxNum, $currNum + 1);
    $random = random_int(1, $maxNum);
?>
<section id="comic" class="bg-white shadow rounded p-6">
    <h2 class="text-xl font-medium mb-2"><?= esc($comic['title']) ?> <span class="text-sm text-gray-500">#<?= esc($comic['num']) ?></span></h2>
    <div class="mb-4"><img class="w-full h-auto rounded" src="<?= esc($comic['img']) ?>" alt="<?= esc($comic['alt']) ?>"></div>
    <div class="mb-4 text-gray-700"><em><?= esc($comic['alt']) ?></em></div>
    <div class="mb-4 text-sm text-gray-500">Date: <?= esc($comic['year']) ?>-<?= esc($comic['month']) ?>-<?= esc($comic['day']) ?></div>

    <div class="flex items-center gap-3 mb-4">
        <button type="button" hx-get="?id=<?= $prev ?>" hx-target="#main" hx-swap="innerHTML" class="px-3 py-1 bg-gray-200 rounded hover:bg-gray-300 cursor-pointer">Previous</button>
        <button type="button" hx-get="?id=<?= $next ?>" hx-target="#main" hx-swap="innerHTML" class="px-3 py-1 bg-gray-200 rounded hover:bg-gray-300 cursor-pointer">Next</button>
        <button type="button" hx-get="?id=<?= $random ?>" hx-target="#main" hx-swap="innerHTML" class="px-3 py-1 bg-gray-200 rounded hover:bg-gray-300 cursor-pointer">Random</button>
        <button type="button" hx-get="/" hx-target="#main" hx-swap="innerHTML" class="px-3 py-1 bg-blue-600 text-white rounded hover:bg-blue-700">Latest</button>
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
<?php endif;

