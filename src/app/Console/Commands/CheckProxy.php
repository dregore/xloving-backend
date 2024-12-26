<?php

namespace App\Console\Commands;

use App\Models\Proxy;
use Illuminate\Console\Command;
use App\Http\Controllers\API\TaskController;

class CheckProxy extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:check-proxy';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Update active status to 1 in proxy every 10 minutes.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        info("Check Proxy Job started at " . now());

        Proxy::where('alive', 1)->update(['active' => 1]);

        info("Check Proxy Job finished at " . now());
    }
}
