<?php

namespace Tests;

use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Symfony\Component\Console\Formatter\OutputFormatter;

class DuskyCommand
{
    protected Collection $script;

    protected ?string $dir = null;

    protected OutputFormatter $formatter;

    public function __construct(protected string $command)
    {
        $this->script = collect();
        $this->formatter = new OutputFormatter(true);
    }

    public function fromDir(string $dir)
    {
        $this->dir = $dir;

        return $this;
    }

    public function enter()
    {
        $this->type('');

        return $this;
    }

    public function waitFor(string $text, int $timeout = 5)
    {
        $this->script->push('set timeout ' . $timeout);

        $text = addslashes($this->formatter->format($text));

        $this->script->push(<<<EXPECT
        expect {
            -ex "$text" { }
            timeout { puts "Not found: $text"; exit 2 }
        }
        EXPECT);

        return $this;
    }

    public function question(string $text, string|int|null $answer = null, int $timeout = 5)
    {
        $this->waitFor($text, $timeout);
        $this->waitFor('>', $timeout);

        $this->type($answer);

        return $this;
    }

    public function selectAccount(string|int|null $answer = null, int $timeout = 1)
    {
        $this->question('Select account', $answer, $timeout);

        return $this;
    }

    public function waitForPlugin(string $pluginName, int $timeout = 5)
    {
        $this->waitFor('Configuring', $timeout)
            ->waitFor($pluginName, $timeout)
            ->waitFor('plugin', $timeout);

        return $this;
    }

    public function waitForConfirmation(string $text, bool $answer = true, int $timeout = 5)
    {
        $this->waitFor($text, $timeout);
        $this->waitFor('>', $timeout);

        $this->type($answer ? 'y' : 'n');

        return $this;
    }

    public function confirm(string $text, int $timeout = 5)
    {
        $this->waitForConfirmation($text, true, $timeout);

        return $this;
    }

    public function deny(string $text, int $timeout = 5)
    {
        $this->waitForConfirmation($text, false, $timeout);

        return $this;
    }

    public function type(?string $text)
    {
        if ($text === null) {
            $text = '';
        }

        $this->script->push('send -- "' . addslashes($text) . '\r"');

        return $this;
    }

    public function exec()
    {
        glob(storage_path('*.exp')) && collect(glob(storage_path('*.exp')))->each(fn ($file) => unlink($file));

        $this->script->prepend(
            'spawn ' . $this->command
        );

        // $this->script->prepend('expect_before { timeout { exit 2 } eof { exit 1 } }');
        $this->script->prepend(<<<'BEFORE'
        expect_before {
            eof { exit 1 }
        }
        BEFORE);

        $this->script->prepend('#!/usr/bin/expect');

        $this->script->push('expect eof');

        $script = $this->script->implode(';');

        $filename = storage_path(Str::random() . '.exp');
        file_put_contents($filename, $this->script->implode(PHP_EOL));
        chmod($filename, 0777);

        // echo $filename . PHP_EOL;

        // dd($filename);

        $cliScript = addslashes($script);
        $cliScript = str_replace(['[', ']'], ['\\[', '\\]'], $cliScript);

        // dd($filename, 'expect -c "' . $cliScript . '"');

        chdir($this->dir ?? base_path());

        while (@ob_end_flush());

        $proc = popen($filename, 'r');
        // $proc = popen('expect -c "' . $cliScript . '"', 'r');

        while (!feof($proc)) {
            $output = fread($proc, 4096);
            echo $output;
            @flush();
        }

        pclose($proc);
    }
}
