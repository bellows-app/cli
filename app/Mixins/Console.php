<?php

namespace Bellows\Mixins;

use Illuminate\Support\Sleep;
use Spatie\Fork\Connection;
use Spatie\Fork\Fork;
use Symfony\Component\Console\Question\Question;

class Console
{
    public function askRequired()
    {
        return function ($question, $default = null, $multiline = false) {
            $question = new Question($question, $default);

            $question->setMultiline($multiline);

            do {
                $answer = $this->output->askQuestion($question);
            } while (trim($answer) === '' || $answer === null);

            return $answer;
        };
    }

    public function anticipateRequired()
    {
        return function ($question, array|callable $choices, $default = null) {
            $question = new Question($question, $default);

            is_callable($choices)
                ? $question->setAutocompleterCallback($choices)
                : $question->setAutocompleterValues($choices);

            do {
                $answer = $this->output->askQuestion($question);
            } while (trim($answer) === '' || $answer === null);

            return $answer;
        };
    }

    public function askForNumber()
    {
        return function ($question, $default = null, $required = false) {
            $question = new Question($question, $default);

            do {
                if (isset($answer) && !is_numeric($answer)) {
                    $this->output->writeln('<warning>Please enter a number.</warning>');
                }

                $answer = $this->output->askQuestion($question);
            } while (($required && (trim($answer) === '' || $answer === null)) || ($answer !== null && !is_numeric($answer)));

            if ($answer === null) {
                return null;
            }

            return (int) $answer;
        };
    }

    public function miniTask()
    {
        return function (string $key, string $value, bool $successful = true) {
            $successIndicator = $successful ? '✓' : '✗';

            $this->output->writeln("<comment>{$successIndicator}</comment> <info>{$key}:</info> <comment>{$value}</comment>");
        };
    }

    public function withSpinner()
    {
        return function (
            string $title,
            callable $task,
            string|callable $message = null,
            string|callable $success = null,
            array $longProcessMessages = []
        ) {
            $this->hideCursor();

            // If they quit, wrap things up nicely (TODO: this isn't working?)
            // $this->trap([SIGTERM, SIGQUIT], function () {
            //     $this->showCursor();
            // });

            // Create a pair of socket connections so the two tasks can communicate
            [$socketToTask, $socketToSpinner] = Connection::createPair();

            $result = app(Fork::class)
                ->run(
                    function () use ($task, $message, $success, $title, $socketToSpinner) {
                        $output = $task();

                        $socketToSpinner->write(1);

                        // Wait for the next cycle of the spinner so that it stops
                        Sleep::for(200_000)->microseconds();

                        $display = '';

                        if (is_callable($message)) {
                            $display = $message($output);
                        } elseif (is_string($message)) {
                            $display = $message;
                        } elseif (is_string($output)) {
                            $display = $output;
                        }

                        if (is_callable($success)) {
                            $wasSuccessful = $success($output);
                        } elseif (is_bool($success)) {
                            $wasSuccessful = $success;
                        } else {
                            // At this point we just assume things worked out
                            $wasSuccessful = true;
                        }

                        $successIndicator = $wasSuccessful ? '✓' : '✗';

                        $finalMessage = $display === '' || $display === null
                            ? "<info>{$title}</info>"
                            : "<info>{$title}:</info> <comment>{$display}</comment>";

                        $this->overwriteLine(
                            "<comment>{$successIndicator}</comment> {$finalMessage}",
                            true,
                        );

                        return $output;
                    },
                    function () use ($longProcessMessages, $title, $socketToTask) {
                        $animation = collect(mb_str_split('⢿⣻⣽⣾⣷⣯⣟⡿'));
                        $startTime = time();

                        $index = 0;

                        if (count($longProcessMessages) === 0) {
                            $longProcessMessages = [
                                3  => 'One moment...',
                                7  => 'Almost done...',
                                11 => 'Wrapping up...',
                            ];
                        }

                        $reversedLongProcessMessages = collect($longProcessMessages)
                            ->reverse()
                            ->map(fn ($v) => ': <comment>' . $v . '</comment>');

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
                                "<comment>{$animation->get($index)}</comment> <info>{$title}{$longProcessMessage}</info>"
                            );

                            Sleep::for(200_000)->microseconds();
                        }
                    }
                );

            $this->showCursor();

            $socketToSpinner->close();
            $socketToTask->close();

            return $result[0];
        };
    }

    public function step()
    {
        return function (string $title) {
            $padding = 2;
            $length = max(40, strlen($title) + ($padding * 2));

            $this->output->newLine();
            $this->output->writeln('<info>+' . str_repeat('-', $length) . '+</info>');
            $this->output->writeln('<info>|' . str_repeat(' ', $length) . '|</info>');
            $this->output->writeln(
                '<info>|'
                    . str_repeat(' ', $padding)
                    . '<comment>' . $title . '</comment>'
                    . str_repeat(' ', $length - strlen($title) - $padding)
                    . '|</info>'
            );
            $this->output->writeln('<info>|' . str_repeat(' ', $length) . '|</info>');
            $this->output->writeln('<info>+' . str_repeat('-', $length) . '+</info>');
            $this->output->newLine();
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
