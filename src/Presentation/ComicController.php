<?php
namespace App\Presentation;

use App\Infra\XkcdApiClient;

class ComicController
{
    private XkcdApiClient $xkcdService;

    public function __construct(XkcdApiClient $xkcdService)
    {
        $this->xkcdService = $xkcdService;
    }

    /**
     * Show a comic by number or the latest if no number is provided.
     */
    public function show(?int $num = null): array
    {
        if ($num === null) {
            // No number provided, show latest
            return $this->xkcdService->getComic();
        }
        if ($num < 1) {
            throw new \Exception('Comic number must be greater than 0.');
        }
        // Try to fetch the requested comic, let errors bubble up
        return $this->xkcdService->getComic($num);
    }
}

