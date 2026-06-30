<?php

use MartinRo\Herdr\Data\Pane;
use MartinRo\Herdr\HerdrManager;

if (! function_exists('herdr')) {
    /**
     * Resolve the Herdr manager, or — when a pane id is given — a handle to
     * that pane. Pass nothing to get the manager itself.
     *
     *   herdr()->panes()->all();
     *   herdr('w1:p1')->run('php artisan test');
     */
    function herdr(?string $paneId = null): HerdrManager|Pane
    {
        /** @var HerdrManager $manager */
        $manager = app('herdr');

        return $paneId === null ? $manager : $manager->pane($paneId);
    }
}
