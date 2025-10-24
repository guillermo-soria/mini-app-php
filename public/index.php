<?php
declare(strict_types=1);

$container = require __DIR__ . '/../src/bootstrap.php';
/** @var \App\Presentation\ComicController $controller */
$controller = $container['controller'];
/** @var \App\Infra\FavoritesRepository $favModel */
$favModel = $container['favModel'];
/** @var \App\Infra\Logger $logger */
$logger = $container['logger'];

$uri = strtok($_SERVER['REQUEST_URI'], '?');

if ($uri === '/api/favorites') {
    header('Content-Type: application/json; charset=utf-8');
    try {
        $favorites = $favModel->getFavorites();
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
        echo json_encode(['error' => 'Failed to fetch favorites', 'trace_id' => $traceId]);
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

if ($error) http_response_code(500);

$addedFavorite = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['favorite']) && $comic) {
    $year = isset($comic['year']) ? filter_var($comic['year'], FILTER_VALIDATE_INT) : false;
    $month = isset($comic['month']) ? filter_var($comic['month'], FILTER_VALIDATE_INT) : false;
    $day = isset($comic['day']) ? filter_var($comic['day'], FILTER_VALIDATE_INT) : false;
    if ($year !== false && $month !== false && $day !== false && $year > 0 && $month >= 1 && $month <= 12 && $day >= 1 && $day <= 31) {
        $date = sprintf('%04d-%02d-%02d', $year, $month, $day);
        $favData = ['num' => $comic['num'], 'title' => $comic['title'], 'img' => $comic['img'], 'alt' => $comic['alt'], 'date' => $date];
        if ($favModel->addFavorite($favData)) $addedFavorite = true;
    } else {
        $logger->error('Invalid date fields for favorite: ' . json_encode([$comic['year'] ?? null, $comic['month'] ?? null, $comic['day'] ?? null]));
    }
}

if (!empty($_SERVER['HTTP_HX_REQUEST'])) {
    include __DIR__ . '/../src/Views/comic_fragment.php';
    exit;
}

?><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>XKCD Mini App</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/htmx.org@1.9.2"></script>
</head>
<body class="bg-gray-50 text-gray-900">
<div class="max-w-3xl mx-auto p-6" id="app">
    <header class="mb-6">
        <h1 class="text-3xl font-semibold">XKCD Mini App</h1>
    </header>

    <main id="main">
        <?php include __DIR__ . '/../src/Views/comic_fragment.php'; ?>
    </main>

    <footer class="mt-6 text-sm text-gray-500">
        <button type="button" onclick="window.open('/api/favorites', '_blank')" class="px-3 py-1 bg-gray-200 rounded hover:bg-gray-300 cursor-pointer">View Favorites (JSON)</button>
    </footer>
</div>

<script>
(function(){
    function isNavAnchor(a){
        if (!a || a.tagName !== 'A') return false;
        var href = a.getAttribute('href') || '';
        if (a.hasAttribute('hx-get')) return true;
        if (href === '/' || href === '' ) return true;
        if (href.startsWith('?') || href.startsWith('/?')) return true;
        if (href === '/api/favorites') return true;
        return false;
    }
    function convertAnchor(a){
        if (!isNavAnchor(a)) return;
        if (a.dataset.converted === '1') return;
        var btn = document.createElement('button');
        btn.type = 'button';
        Array.from(a.attributes).forEach(function(attr){
            var name = attr.name, value = attr.value;
            if (name === 'href') {
                if (value === '/api/favorites' || a.target === '_blank') {
                    btn.addEventListener('click', function(ev){ ev.preventDefault(); window.open(value, a.target || '_blank'); });
                } else {
                    btn.setAttribute('hx-get', value);
                }
            } else if (name.startsWith('hx-') || name === 'class' || name === 'role' || name.startsWith('aria-') || name.startsWith('data-')) {
                btn.setAttribute(name, value);
            }
        });
        btn.innerHTML = a.innerHTML;
        if (a.hasAttribute('title')) btn.setAttribute('title', a.getAttribute('title'));
        if (a.hasAttribute('tabindex')) btn.setAttribute('tabindex', a.getAttribute('tabindex'));
        btn.dataset.converted = '1';
        a.replaceWith(btn);
    }
    function convertAnchors(root){ root = root || document; var anchors = root.querySelectorAll('a'); anchors.forEach(convertAnchor); }
    function observeAndConvert(){ convertAnchors(document); var mo = new MutationObserver(function(mutations){ mutations.forEach(function(m){ m.addedNodes && m.addedNodes.forEach(function(node){ if (node.nodeType === 1) convertAnchors(node); }); }); }); mo.observe(document.documentElement || document.body, { childList: true, subtree: true }); document.body.addEventListener('htmx:afterSwap', function(evt){ var tgt = evt && (evt.target || (evt.detail && evt.detail.target)); convertAnchors(tgt || document); }); }
    if (document.readyState === 'loading') { document.addEventListener('DOMContentLoaded', observeAndConvert); } else { observeAndConvert(); }
})();
</script>
</body>
</html>
