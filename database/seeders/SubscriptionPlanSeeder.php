<?php

namespace Database\Seeders;

use App\Models\Plan;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SubscriptionPlanSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function (): void {
            foreach ([
                ['standard', 'Standard', 1, 1900, 4500, 3, 3, 2, 30, ['Acces la oportunități Standard']],
                ['premium', 'Premium', 2, 3900, 10500, 7, 7, 6, 0, ['Acces la oportunități Standard și Premium']],
                ['business', 'Business', 3, 4900, 13500, null, null, null, 0, ['Acces la oportunități Standard, Premium și Business']],
            ] as [$key, $name, $level, $monthly, $quarterly, $total, $explorer, $mentor, $trial, $benefits]) {
                Plan::unguarded(fn () => Plan::firstOrCreate(['seed_key' => $key], [
                    'name' => $name, 'access_level' => $level, 'monthly_price_minor' => $monthly,
                    'quarterly_price_minor' => $quarterly, 'max_projects' => $total,
                    'max_explorer_projects' => $explorer, 'max_mentor_projects' => $mentor,
                    'trial_days' => $trial, 'benefits' => $benefits,
                ]));
            }
        });
    }
}
