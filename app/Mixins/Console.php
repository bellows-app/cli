<?php

namespace App\Mixins;

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

            // TODO: How do we do this better... this is a hack and who knows what the filesystem writability will be
            $cachePath = storage_path('app/' . time());

            file_put_contents($cachePath, '1');

            // If they quit, wrap things up nicely (TODO: this isn't working?)
            $this->trap([SIGTERM, SIGQUIT], function () use ($cachePath) {
                $this->showCursor();
                unlink($cachePath);
            });

            $result = Fork::new()
                ->run(
                    function () use ($task, $successDisplay, $title, $cachePath) {
                        $output = $task();

                        unlink($cachePath);

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
                            "<info>{$title}: {$display}</info>",
                            true,
                        );

                        return $output;
                    },
                    function () use ($longProcessMessages, $title, $cachePath) {
                        $animation = collect(mb_str_split('⢿⣻⣽⣾⣷⣯⣟⡿'));
                        $startTime = time();

                        $index = 0;

                        // $this->output->write($title . ': ' . $animation->get($index));

                        $reversedLongProcessMessages = collect($longProcessMessages)
                            ->reverse()
                            ->map(fn ($v) => ' ' . $v);

                        while (file_exists($cachePath)) {
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
