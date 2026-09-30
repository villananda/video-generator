<?php

use App\Models\Video;
use App\Services\VideoService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('model:prune', ['--model' => Video::class])->daily();

Schedule::call(fn () => app(VideoService::class)->failStuckVideos())
    ->name('fail-stuck-videos')
    ->everyFiveMinutes();
