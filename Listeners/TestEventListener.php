<?php

namespace Modules\SmartAcars\Listeners;

use App\Events\TestEvent;
use Log;

class TestEventListener
{
    /**
     * Handle the event.
     */
    public function handle(TestEvent $event) {
        Log::info('Received event', [$event]);
    }
}
