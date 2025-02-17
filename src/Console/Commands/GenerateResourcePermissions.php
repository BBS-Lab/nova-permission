<?php

declare(strict_types=1);

namespace BBSLab\NovaPermission\Console\Commands;

use BBSLab\NovaPermission\Actions\GenerateResourcePermissionsAction;
use Illuminate\Console\Command;

class GenerateResourcePermissions extends Command
{
    protected $signature = 'nova-permission:generate';

    protected $description = 'Generate resource permissions';

    public function handle(GenerateResourcePermissionsAction $action): int
    {
        $this->comment('Generating permissions');

        $action->execute();

        $this->info('Permission generated');

        return self::SUCCESS;
    }
}
