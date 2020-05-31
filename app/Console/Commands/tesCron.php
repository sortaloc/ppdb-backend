<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class tesCron extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'tes:cron';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        //
        file_put_contents(__DIR__."../../../../tmp/tes-cron-job.txt", date('Y-m-d H:i:s'));
    }
}
