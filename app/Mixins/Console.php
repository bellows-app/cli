<?php

namespace App\Mixins;

use Spatie\Fork\Connection;
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

            // If they quit, wrap things up nicely (TODO: this isn't working?)
            $this->trap([SIGTERM, SIGQUIT], function () {
                $this->showCursor();
            });

            // Create a pair of socket connections so the two tasks can communicate
            [$socketToTask, $socketToSpinner] = Connection::createPair();

            $result = Fork::new()
                ->run(
                    function () use ($task, $successDisplay, $title,  $socketToSpinner) {
                        $output = $task();

                        $socketToSpinner->write(1);

                        // Wait for the next cycle of the spinner so that it stops
                        usleep(200_000);

                        if (is_callable($successDisplay)) {
                            $display = $successDisplay($output);
                        } else if (is_string($successDisplay)) {
                            $display = $successDisplay;
                        } else if (is_string($output)) {
                            $display = $output;
                        } else {
                            $display = '✓';
                        }

                        $this->overwriteLine(
                            "<info>{$title}:</info> <comment>{$display}</comment>",
                            true,
                        );

                        return $output;
                    },
                    function () use ($longProcessMessages, $title, $socketToTask) {
                        $animation = collect(mb_str_split('⢿⣻⣽⣾⣷⣯⣟⡿'));
                        $startTime = time();

                        $index = 0;

                        $reversedLongProcessMessages = collect($longProcessMessages)
                            ->reverse()
                            ->map(fn ($v) => ' ' . $v);

                        $socketResults = '';

                        while (!$socketResults) {
                            foreach ($socketToTask->read() as $output) {
                                $socketResults .= $output;
                            }

                            $runningTime = 0;
                            $runningTime = time() - $startTime;

                            $longProcessMessage = $reversedLongProcessMessages->first(
                                fn ($v, $k) => $runningTime >= $k
                            ) ?? '';

                            $index = ($index === $animation->count() - 1) ? 0 : $index + 1;

                            $this->overwriteLine(
                                "<info>{$title}:</info> <comment>{$animation->get($index)}{$longProcessMessage}</comment>"
                            );

                            usleep(200_000);
                        }
                    }
                );

            $this->showCursor();

            return $result[0];
        };
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
