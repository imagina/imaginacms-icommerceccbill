<?php

namespace Modules\Icommerceccbill\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Isite\Jobs\ProcessSeeds;

class IcommerceccbillDatabaseSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        ProcessSeeds::dispatch([
            'baseClass' => "\Modules\Icommerceccbill\Database\Seeders",
            'seeds' => ['IcommerceccbillModuleTableSeeder', 'IcommerceccbillSeeder'],
        ]);
    }
}
