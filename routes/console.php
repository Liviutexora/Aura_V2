<?php

use App\Models\MissionReminder;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::call(function (): void {
    MissionReminder::where('status', 'scheduled')->where('remind_at', '<=', now())
        ->chunkById(100, function ($reminders): void {
            foreach ($reminders as $candidate) {
                DB::transaction(function () use ($candidate): void {
                    $reminder = MissionReminder::lockForUpdate()->find($candidate->id);
                    if (! $reminder || $reminder->revision !== $candidate->revision || $reminder->status !== 'scheduled' || $reminder->remind_at->isFuture()) {
                        return;
                    }
                    $mission = $reminder->mission;
                    $mission->project->events()->create(['mission_id' => $mission->id, 'event_type' => 'reminder_sent',
                        'event_key' => 'reminder:'.$reminder->id.':'.$reminder->revision, 'occurred_at' => now(), 'metadata' => ['title' => $mission->title]]);
                    $reminder->update(['status' => 'sent', 'sent_at' => now()]);
                }, 3);
            }
        });
})->name('mission-reminders')->everyMinute();

Schedule::command('auth:clear-resets')->everyFifteenMinutes();
