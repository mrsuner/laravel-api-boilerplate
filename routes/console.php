<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('files:cleanup')
    ->hourly()
    ->when(fn (): bool => (bool) config('boilerplate.files.cleanup.enabled', true));
