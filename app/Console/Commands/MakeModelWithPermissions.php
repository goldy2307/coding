<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Spatie\Permission\Models\Permission;

class MakeModelWithPermissions extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'make:modelx {name} {--m}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create model and generate CRUD permissions';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $name = $this->argument('name');
        $withMigration = $this->option('m') ? '-m' : '';

        // Create model
        Artisan::call("make:model {$name} {$withMigration}");
        $this->info("Model {$name} created.");

        // Generate permissions
        $actions = ['create', 'view', 'edit', 'delete'];
        foreach ($actions as $action) {
            $permission = "{$action} " . strtolower($name);
            Permission::firstOrCreate(['name' => $permission]);
        }

        $this->info("CRUD permissions for {$name} created.");
    }
}
