<?php

namespace Bellows\Commands;

use LaravelZero\Framework\Commands\Command;

class Playground extends Command
{
    protected $signature = 'playground';

    protected $description = 'Go ahead. Play around.';

    public function handle()
    {
        $process = proc_open('./bellows kickoff', [
            ['pipe', 'r'],  // stdin is a pipe that the child will read from
            ['pipe', 'w'],  // stdout is a pipe that the child will write to
            ['file', __DIR__ . '/error-output.txt', 'a'], // stderr is a file to write to
        ], $pipes);

        if (is_resource($process)) {
            // Hm, how do we get the question that we expecting?
            fwrite($pipes[0], "gary\n");
            fwrite($pipes[0], "dev\n");
            fwrite($pipes[0], "new york\n");
            fclose($pipes[0]);

            echo stream_get_contents($pipes[1]);
            fclose($pipes[1]);

            // It is important that you close any pipes before calling
            // proc_close in order to avoid a deadlock
            $return_value = proc_close($process);

            echo "command returned $return_value\n";
        }
    }
}
