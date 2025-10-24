<?php
declare(strict_types=1);

$xkcdBase = 'https://xkcd.com';
$timeout  = 6;

/**
 * Llama al endpoint de XKCD. Si $num es null → cómic actual.
 * Devuelve array con datos o null si hay error.
 */
function fetchXkcd(?int $num, string $base, int $timeout): ?array {
    $url = $num ? "{$base}/{$num}/info.0.json" : "{$base}/info.0.json";

    $ch = curl_init($url);
    curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => $timeout,
            CURLOPT_TIMEOUT        => $timeout,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_USERAGENT      => 'xkcd-miniapp/step2',
    ]);
    $res = curl_exec($ch);

    if ($res === false) {
        curl_close($ch);
        return null; // error de red
    }

    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($status !== 200) {
        return null; // p.ej. 404 si el número no existe
    }

    $data = json_decode($res, true);
    return is_array($data) ? $data : null;
}

function htmlError(string $msg): void {
    $safe = htmlspecialchars($msg, ENT_QUOTES, 'UTF-8');
    http_response_code(502);
    echo <<<HTML
<!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Error – XKCD</title>
<style>body{font-family:system-ui;margin:24px;color:#b00020}a{color:#0b5}</style>
</head><body>
<h1>Something went wrong</h1>
<p>{$safe}</p>
<p><a href="/">Go home</a></p>
</body></html>
HTML;
    exit;
}

/** 1) Traer el “latest” para saber el límite superior */
$latest = fetchXkcd(null, $xkcdBase, $timeout);
if (!$latest) {
    htmlError('Network/API error reaching XKCD for the latest comic.');
}
$latestNum = (int)($latest['num'] ?? 1);

/** 2) Leer ?id y acotarlo a [1, $latestNum] */
$requested = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if ($requested !== null && $requested !== false) {
    $requested = max(1, min($latestNum, $requested));
} else {
    $requested = null; // null → actual
}

/** 3) Traer el cómic solicitado (o actual si null) */
$comic = fetchXkcd($requested, $xkcdBase, $timeout);
if (!$comic) {
    htmlError('Comic not found or network/API error.');
}

/** 4) Datos y fecha robusta */
$num   = (int)($comic['num'] ?? 0);
$title = htmlspecialchars($comic['title'] ?? 'Unknown', ENT_QUOTES, 'UTF-8');
$img   = htmlspecialchars($comic['img'] ?? '', ENT_QUOTES, 'UTF-8');
$alt   = htmlspecialchars($comic['alt'] ?? '', ENT_QUOTES, 'UTF-8');

$y = (int)($comic['year'] ?? 0);
$m = (int)($comic['month'] ?? 0);
$d = (int)($comic['day'] ?? 0);

if ($y >= 1 && checkdate($m ?: 1, $d ?: 1, $y)) {
    $dt = DateTimeImmutable::createFromFormat('!Y-n-j', "{$y}-{$m}-{$d}");
    $date = $dt ? $dt->format('Y-m-d') : sprintf('%04d-%02d-%02d', $y, max(1,$m), max(1,$d));
} else {
    $date = '0000-01-01';
}

/** 5) Navegación (prev/next/random) con límites */
$prev   = max(1, $num - 1);
$next   = min($latestNum, $num + 1);
$rand   = random_int(1, $latestNum);
$disPrev = $num <= 1 ? 'disabled' : '';
$disNext = $num >= $latestNum ? 'disabled' : '';

?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title><?= $title ?> – XKCD</title>
    <style>
        body{font-family:system-ui, -apple-system, Segoe UI, Roboto; margin:24px}
        .wrap{max-width:920px;margin:auto}
        figure{margin:16px 0}
        img{max-width:100%;height:auto}
        nav a button{margin-right:8px;padding:8px 12px}
        .meta{color:#555}
        .badge{display:inline-block;background:#eee;border-radius:6px;padding:2px 8px;margin-left:8px}
        form.inline{display:inline}
        input[type=number]{width:110px}
    </style>
</head>
<body>
<div class="wrap">
    <h1><?= $title ?> <span class="badge">#<?= $num ?></span></h1>
    <p class="meta">Date: <?= htmlspecialchars($date, ENT_QUOTES, 'UTF-8') ?> · Latest: <?= $latestNum ?></p>

    <figure>
        <img src="<?= $img ?>" alt="<?= $alt ?>" title="<?= $alt ?>">
        <figcaption><?= $alt ?></figcaption>
    </figure>

    <nav>
        <a href="/?id=<?= $prev ?>"><button <?= $disPrev ?>>Previous</button></a>
        <a href="/?id=<?= $next ?>"><button <?= $disNext ?>>Next</button></a>
        <a href="/?id=<?= $rand ?>"><button>Random</button></a>

        <form class="inline" method="get" action="/">
            <label>
                <input type="number" name="id" min="1" max="<?= $latestNum ?>" placeholder="Go to #" >
            </label>
            <button type="submit">Go</button>
        </form>

        <a href="/"><button>Today</button></a>
    </nav>
</div>
</body>
</html>
