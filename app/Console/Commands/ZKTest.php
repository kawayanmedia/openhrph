<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Rats\Zkteco\Lib\ZKTeco;

class ZKTest extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'zk:test';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test ZK connections';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {

        // https://packagist.org/packages/rats/zkteco
        $zk = new ZKTeco('192.168.254.125');

        if ($zk->connect()) {
            $this->comment($zk
                ->osVersion());
            // $zk->restart();

            $logs = $zk->getAttendance();

            foreach ($logs as $log) {
                // $this->comment($log);
                dd($log);
                var_dump($log);
            }

            // var_dump($zk->getAttendance());
            $zk->testVoice();
            $zk->shutdown();
            $zk->disconnect();
        }

        // $zk->connect();



        return Command::SUCCESS;
    }
}
