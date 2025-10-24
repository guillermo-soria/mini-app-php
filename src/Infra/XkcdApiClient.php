<?php
namespace App\Infra;

use Exception;
use App\Infra\Logger;

class XkcdApiClient
{
    private string $baseUrl = 'https://xkcd.com';
    private Logger $logger;

    public function __construct(Logger $logger)
    {
        $this->logger = $logger;
    }

    /**
     * Fetch a comic by number or the latest if no number is provided.
     */
    public function getComic(?int $num = null): array
    {
        $url = $this->baseUrl;
        if ($num !== null) {
            $url .= "/{$num}";
        }
        $url .= "/info.0.json";

        $this->logger->info("Requesting XKCD comic: " . ($num ?? 'latest') . " URL: $url");

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        $json = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        if (curl_errno($ch) || $httpCode !== 200) {
            $errorMsg = "Could not fetch XKCD comic. Network error or invalid response. Comic: " . ($num ?? 'latest') . " URL: $url HTTP: $httpCode";
            $this->logger->error($errorMsg);
            curl_close($ch);
            throw new Exception($errorMsg);
        }
        curl_close($ch);
        $data = json_decode($json, true);
        if (!is_array($data)) {
            $errorMsg = "Invalid response from XKCD API. Comic: " . ($num ?? 'latest') . " URL: $url";
            $this->logger->error($errorMsg);
            throw new Exception($errorMsg);
        }
        return $data;
    }

    /**
     * Get the latest comic number from XKCD.
     */
    public function getLatestComicNum(): int
    {
        $latestComic = $this->getComic();
        return isset($latestComic['num']) ? (int)$latestComic['num'] : 1;
    }
}

