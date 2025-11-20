<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Schedule untuk reminder discount event expiration dan refresh status bakery
Schedule::command('discounts:expire')->everyMinute();
Schedule::command('bakeries:refresh-discount-status')->everyFiveMinutes();
