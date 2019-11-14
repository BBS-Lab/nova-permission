<?php

namespace BBSLab\NovaPermission\Console\Commands;

use BBSLab\NovaPermission\Actions\GenerateResourcePermissionsAction;
use Illuminate\Console\Command;

class GenerateResourcePermissions extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'nova-permission:generate';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate resource permissions';

    /**
     * Execute the console command.
     *
     * @param  \BBSLab\NovaPermission\Actions\GenerateResourcePermissionsAction  $action
     * @return mixed
     */
    public function handle(GenerateResourcePermissionsAction $action)
    {
        $this->comment('Generating permissions');

        $action->execute();

        $this->info('Permission generated');
    }
}
