<?php

namespace App\Mixins;

use Spatie\Async\Pool;
use Spatie\Fork\Fork;

class Console
{
    public function withSpinner()
    {
        return function (
            string $title,
            callable $task,
            string|callable $successDisplay = null,
            array $longProcessMessages = []
        ) {
            $this->hideCursor();

            file_put_contents(storage_path('app/running'), 'true');

            $result = Fork::new()
                ->run(
                    function () use ($task, $successDisplay, $title) {
                        $output = $task();

                        file_put_contents(storage_path('app/running'), 'false');

                        if (is_callable($successDisplay)) {
                            $message = $successDisplay($output);
                        } else if (is_string($successDisplay)) {
                            $message = $successDisplay;
                        } else if (is_string($output)) {
                            $message = $output;
                        } else {
                            $message = '✓';
                        }

                        $this->overwriteLine(
                            $title . ': <info>' . $message . '</info>',
                            true,
                        );

                        return $output;
                    },
                    function () use ($longProcessMessages, $title) {
                        $animation = collect(mb_str_split('⢿⣻⣽⣾⣷⣯⣟⡿'));
                        $startTime = time();

                        $index = 0;

                        $this->output->write($title . ': ' . $animation->get($index));

                        $reversedLongProcessMessages = collect($longProcessMessages)
                            ->reverse()
                            ->map(fn ($v) => ' ' . $v);

                        while (file_get_contents(storage_path('app/running')) === 'true') {
                            $runningTime = 0;
                            $runningTime = time() - $startTime;

                            $longProcessMessage = $reversedLongProcessMessages->first(
                                fn ($v, $k) => $runningTime >= $k
                            ) ?? '';

                            $index = ($index === $animation->count() - 1) ? 0 : $index + 1;

                            $this->overwriteLine(
                                $title . ': <comment>' . $animation->get($index) . $longProcessMessage . '</comment>'
                            );

                            usleep(200000);
                        }
                    }
                );

            $this->showCursor();

            unlink(storage_path('app/running'));

            return $result[0];

            [$first, $second] = $result;
            dd($first, $second);

            $this->hideCursor();

            $running = true;
            $animation = collect(mb_str_split('⢿⣻⣽⣾⣷⣯⣟⡿'));

            $index = 0;

            $this->output->write($title . ': ' . $animation->get($index));

            $pool = Pool::create();

            $process = $pool->add(function () use ($title, $animation, $index,) {
                $stream = fopen('php://stdout', 'w');
                $count = 0;
                while ($count < 10) {

                    fwrite($stream, $count);
                    // fwrite($stream, $title . ': ' . $animation->get($index));

                    fflush($stream);

                    $count++;

                    sleep(1);
                }
                // $this->output->write($title . ': ' . $animation->get($index));

                // sleep(10);
            });

            // if (!$process->isRunning()) {
            //     $process->start();
            // }

            $task();

            // $process->stop();

            return;

            // $process->then(function ($output)  use (
            //     &$running,
            //     $title,
            //     $successDisplay,
            // ) {
            //     $running = false;

            //     if (is_callable($successDisplay)) {
            //         $message = $successDisplay($output);
            //     } else if (is_string($successDisplay)) {
            //         $message = $successDisplay;
            //     } else if (is_string($output)) {
            //         $message = $output;
            //     } else {
            //         $message = '✓';
            //     }

            //     $this->overwriteLine(
            //         $title . ': <info>' . $message . '</info>',
            //         true,
            //     );
            // })->catch(function ($exception) use (
            //     &$running,
            //     $title,
            // ) {
            //     $running = false;

            //     $this->overwriteLine(
            //         $title . ': <error>' . $exception->getMessage() . '</error>',
            //         true,
            //     );

            //     throw $exception;
            // });

            // // If they quit, wrap things up nicely
            // $this->trap([SIGTERM, SIGQUIT], function () use ($process) {
            //     $this->showCursor();
            //     $process->stop();
            // });

            // $reversedLongProcessMessages = collect($longProcessMessages)
            //     ->reverse()
            //     ->map(fn ($v) => ' ' . $v);

            // while ($running) {
            //     $runningTime = floor($process->getCurrentExecutionTime());

            //     $longProcessMessage = $reversedLongProcessMessages->first(
            //         fn ($v, $k) => $runningTime >= $k
            //     ) ?? '';

            //     $index = ($index === $animation->count() - 1) ? 0 : $index + 1;

            //     $this->overwriteLine(
            //         $title . ': <comment>' . $animation->get($index) . $longProcessMessage . '</comment>'
            //     );

            //     usleep(200000);
            // }

            // $this->showCursor();

            // return $process->getOutput();
        };
    }

    private function runTask($task, $longProcessMessage)
    {
        sleep(3);

        $this->running = false;

        return 'runnnnTask';
    }

    private function okSure()
    {
        while ($this->running) {
            ray($this->running);
            $this->output->writeLn('Is this working? At all?');
            sleep(1);
        }

        return 'ooooookSure';
    }

    public function overwriteLine()
    {
        return function (string $message, bool $newLine = false) {
            // Move the cursor to the beginning of the line
            $this->output->write("\x0D");

            // Erase the line
            $this->output->write("\x1B[2K");

            $this->output->write($message);

            if ($newLine) {
                $this->newLine();
            }
        };
    }

    public function hideCursor()
    {
        return function () {
            $this->output->write("\e[?25l");
        };
    }

    public function showCursor()
    {
        return function () {
            $this->output->write("\e[?25h");
        };
    }
}
