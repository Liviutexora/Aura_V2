<?php

namespace App\Providers;

use App\Models\ProjectEvent;
use App\Models\ProjectMission;
use App\Models\User;
use App\Services\OpportunityCatalog;
use App\Services\UserHeaderStatus;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\View\View;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Authentication endpoints use Laravel's numeric throttle middleware.
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        \Illuminate\Support\Facades\View::composer('components.aura.user-header', function (View $view): void {
            $view->with('headerIndicator', app(UserHeaderStatus::class)->for(auth()->user()));
        });
        \Illuminate\Support\Facades\View::composer('layouts.project', function (View $view): void {
            $view->with('missionAlerts', ProjectEvent::whereHas('project', fn ($q) => $q->where('user_id', auth()->id()))
                ->where('event_type', 'reminder_sent')->latest('id')->limit(10)->get());
        });
        \Illuminate\Support\Facades\View::composer('admin.layouts.app', function (View $view): void {
            if ($view->getData()['projectAdmin'] ?? false) {
                $view->with('demoData', ['nav' => json_decode(file_get_contents(resource_path('data/admin-navigation.json')), true, 512, JSON_THROW_ON_ERROR)]);
            }
        });
        \Illuminate\Support\Facades\View::composer('admin.pages.dashboard', function (View $view): void {
            $view->with('registeredUsersCount', User::query()->count());
        });

        \Illuminate\Support\Facades\View::composer(['admin.pages.dashboard', 'admin.pages.analytics', 'admin.pages.financial-reports'], function (View $view): void {
            $available = app(OpportunityCatalog::class)->published();
            $missionCount = ProjectMission::count();
            $completed = ProjectMission::where('status', 'completed')->count();
            $view->with('projectSummary', ['active' => (clone $available)->count(),
                'completed' => $completed, 'completionRate' => $missionCount ? round(100 * $completed / $missionCount, 1) : 0,
                'offers' => $available->withCount('projects')->orderByDesc('is_featured')->latest('id')->limit(4)
                    ->get()->map->only(['id', 'title', 'payer', 'icon', 'projects_count'])->all()]);
        });

        RateLimiter::for('admin-password-reset', function (Request $request): Limit {
            $adminIdentifier = $request->user()?->getAuthIdentifier() ?? 'guest';

            return Limit::perMinute(5)->by($adminIdentifier.'|'.$request->ip());
        });
    }
}
