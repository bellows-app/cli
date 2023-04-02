<?php

namespace Tests;

use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class DuskyCommand
{
    protected Collection $script;

    protected ?string $dir = null;

    public function __construct(protected string $command)
    {
        $this->script = collect();
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
        $this->script->push('expect "' . addslashes($text) . '"');

        return $this;
    }

    public function confirm()
    {
        $this->type('y');

        return $this;
    }

    public function deny()
    {
        $this->type('n');

        return $this;
    }

    public function type(string $text)
    {
        $this->script->push('send -- "' . addslashes($text) . '\r"');

        return $this;
    }

    public function exec()
    {
        $this->script->prepend(
            'spawn ' . $this->command
        );

        $this->script->prepend('expect_before { timeout { exit 2 } eof { exit 1 } }');

        $this->script->push('expect eof');

        $script = $this->script->implode(';');

        $cliScript = addslashes($script);
        $cliScript = str_replace(['[', ']'], ['\\[', '\\]'], $cliScript);

        ray($cliScript);

        chdir($this->dir ?? base_path());

        while (@ob_end_flush());

        $proc = popen('expect -c "' . $cliScript . '"', 'r');

        while (!feof($proc)) {
            echo fread($proc, 4096);
            @flush();
        }

        pclose($proc);
    }
}
