<?php
declare(strict_types=1);

use App\Infra\Logger;
use App\Infra\XkcdApiClient;
use App\Presentation\ComicController;
use App\Infra\FavoritesRepository;

require_once __DIR__ . '/Infra/Logger.php';
require_once __DIR__ . '/Infra/XkcdApiClient.php';
require_once __DIR__ . '/Infra/FavoritesRepository.php';
require_once __DIR__ . '/Presentation/ComicController.php';

$dbPath = getenv('FAVORITES_DB') ?: (__DIR__ . '/../data/favorites.sqlite');
$logPath = __DIR__ . '/../logs/app.log';

$dbDir = dirname($dbPath);
if (!is_dir($dbDir)) {
    mkdir($dbDir, 0775, true);
}

$pdo = new PDO('sqlite:' . $dbPath);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$logger = new Logger($logPath);
$xkcdService = new XkcdApiClient($logger);
$controller = new ComicController($xkcdService);
$favModel = new FavoritesRepository($pdo);

function esc($str) {
    return htmlspecialchars((string)$str, ENT_QUOTES, 'UTF-8');
}

return [
    'pdo' => $pdo,
    'logger' => $logger,
    'xkcdService' => $xkcdService,
    'controller' => $controller,
    'favModel' => $favModel,
];

