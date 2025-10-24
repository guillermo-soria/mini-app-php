<?php
declare(strict_types=1);

$xcdBase = 'https://xkcd.com';
$timeout = 5;

function fetchXkcdComic(?int $comicNumber, string $baseUrl, int $timeout): ?array {
    $url = $baseUrl;
    if ($comicNumber !== null) {
        $url .= '/' . $comicNumber;
    }
    $url .= '/info.0.json';
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
    $response = curl_exec($ch);
    if (curl_errno($ch)) {
        curl_close($ch);
        return null;
    }
    curl_close($ch);
    return json_decode($response, true);
}

function htmlError(string $msg): string {
    $safe = htmlspecialchars($msg, ENT_QUOTES, 'UTF-8');
    return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Error</title>
</head>
<body>
    <h1>Something went wrong</h1>
    <p>{$safe}</p>
    <p><a href="index.php">Go back to the main page</a></p>
</body>
</html>
HTML;
}

//Obtener comic
$comic = fetchXkcdComic(null, $xcdBase, $timeout);
//Render
$num = (int)($comic['num'] ?? 0);
$title = htmlspecialchars($comic['title'] ?? 'Unknown', ENT_QUOTES, 'UTF-8');
$imgUrl = htmlspecialchars($comic['img'] ?? '', ENT_QUOTES, 'UTF-8');
$altText = htmlspecialchars($comic['alt'] ?? '', ENT_QUOTES, 'UTF-8');
$year  = (int)($comic['year']  ?? 0);
$month = (int)($comic['month'] ?? 0);
$day   = (int)($comic['day']   ?? 0);

if ($year >= 1 && checkdate($month ?: 1, $day ?: 1, $year)) {
    // '!' resetea hora a 00:00:00 y evita heredar la hora actual
    $dt = DateTimeImmutable::createFromFormat('!Y-n-j', "{$year}-{$month}-{$day}");
    // Si por alguna razón fallara el parseo, caemos a un sprintf seguro
    $date = $dt ? $dt->format('Y-m-d')
        : sprintf('%04d-%02d-%02d', $year, max(1,$month), max(1,$day));
} else {
    // Fallback si faltan datos o la fecha no es válida
    $date = '0000-01-01';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $title; ?> - XKCD Comic</title>
</head>
<body>
    <div class="wrap" >
        <h1><?php echo $title; ?></h1>
        <div class="comic">
            <img src="<?php echo $imgUrl; ?>" alt="<?php echo $altText; ?>" title="<?php echo $altText; ?>">
        </div>
        <p>Comic Number: <?php echo $num; ?></p>
        <p>Date: <?php echo $date; ?></p>
        <p><a href="index.php">Load another comic</a></p>
    </div>
</body>
</html>
