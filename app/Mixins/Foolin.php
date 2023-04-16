<?php

namespace Bellows\Mixins;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Symfony\Component\Console\Cursor;
use Symfony\Component\Console\Formatter\OutputFormatterStyle;

class Foolin
{
    public function interactiveChoice()
    {
        return function (string $question, array $items, $default = null, $multiple = false, $validator = null, string $errorMessage = null) {
            $inputStream = fopen('php://stdin', 'rb');

            $cursor = new Cursor($this->output, $inputStream);

            $sttyMode = shell_exec('stty -g');
            $isStdin = 'php://stdin' === (stream_get_meta_data($inputStream)['uri'] ?? null);
            $r = [$inputStream];
            $w = [];
            $index = 0;
            $selected = $default === null ? collect() : collect(is_array($default) ? $default : [$default]);
            $cursor->hide();

            $items = collect($items);

            shell_exec('stty -icanon -echo');

            $this->output->getFormatter()->setStyle('focused', new OutputFormatterStyle('black', null, ['bold']));
            $this->output->getFormatter()->setStyle('unfocused', new OutputFormatterStyle('gray'));
            $this->output->getFormatter()->setStyle('radio_selected', new OutputFormatterStyle('green'));
            $this->output->getFormatter()->setStyle('radio_unselected', new OutputFormatterStyle('gray'));

            $this->output->writeln($this->dim('│'));
            $this->output->writeln(($errorMessage ? $this->warning('▲') : $this->active('◆')) . " {$question}");
            $this->writeChoices($cursor, $items, $index, $selected, $multiple, $errorMessage, true);

            // Read a keypress
            while (!feof($inputStream)) {
                while ($isStdin && 0 === @stream_select($r, $w, $w, 0, 100)) {
                    // Give signal handlers a chance to run
                    $r = [$inputStream];
                }

                $c = fread($inputStream, 1);

                // as opposed to fgets(), fread() returns an empty string when the stream content is empty, not false.
                if (false === $c || '' === $c) {
                    $cursor->show();
                    shell_exec('stty ' . $sttyMode);
                    throw new \Exception('Aborted.');
                } elseif ("\033" === $c) {
                    // Did we read an escape sequence?
                    $c .= fread($inputStream, 2);

                    // A = Up Arrow. B = Down Arrow
                    match ($c[2] ?? null) {
                        'A' => $index--, // Up
                        'B' => $index++, // Down
                        'C' => $index--, // Left
                        'D' => $index++, // Right
                    };

                    if ($index < 0) {
                        $index = count($items) - 1;
                    } elseif ($index >= count($items)) {
                        $index = 0;
                    }

                    $this->writeChoices($cursor, $items, $index, $selected, $multiple, $errorMessage);
                } elseif ($c === ' ') {
                    if (!$multiple) {
                        $selected = collect([$index]);
                    } elseif ($selected->contains($index)) {
                        $selected = $selected->reject(fn ($i) => $i === $index);
                    } else {
                        $selected->push($index);
                    }

                    $this->writeChoices($cursor, $items, $index, $selected, $multiple, $errorMessage);
                } elseif ($c === "\n") {
                    break;
                }
            }

            if ($validator) {
                try {
                    Validator::make(['value' => $selected], ['value' => $validator])->validate();
                } catch (ValidationException $e) {
                    $cursor->moveUp($items->count() + 3);
                    $cursor->clearOutput();

                    return $this->interactiveChoice($question, $items->toArray(), $default, $multiple, $validator, $e->getMessage());
                }
            }

            $selectedItems = $selected->map(fn ($i) => $items[$i]);

            $cursor->moveUp($items->count() + 2);

            $cursor->clearOutput();

            $this->output->writeln($this->active('◇') . " {$question}");
            $this->output->writeln($this->dim('│ ' . $selectedItems->join(', ')));

            $cursor->show();

            shell_exec('stty ' . $sttyMode);

            return $selectedItems->toArray();
        };
    }

    public function interactiveConfirm()
    {
        return function (string $question, $default = false) {
            $inputStream = fopen('php://stdin', 'rb');

            $cursor = new Cursor($this->output, $inputStream);

            $sttyMode = shell_exec('stty -g');
            $isStdin = 'php://stdin' === (stream_get_meta_data($inputStream)['uri'] ?? null);
            $r = [$inputStream];
            $w = [];
            $selected = $default;
            $cursor->hide();

            shell_exec('stty -icanon -echo');

            $this->output->getFormatter()->setStyle('focused', new OutputFormatterStyle('black', null, ['bold']));
            $this->output->getFormatter()->setStyle('unfocused', new OutputFormatterStyle('gray'));
            $this->output->getFormatter()->setStyle('radio_selected', new OutputFormatterStyle('green'));
            $this->output->getFormatter()->setStyle('radio_unselected', new OutputFormatterStyle('gray'));

            $this->output->writeln($this->dim('│'));
            $this->output->writeln($this->active('◆') . " {$question}");
            $this->writeConfirm($cursor, $selected, true);

            // Read a keypress
            while (!feof($inputStream)) {
                while ($isStdin && 0 === @stream_select($r, $w, $w, 0, 100)) {
                    // Give signal handlers a chance to run
                    $r = [$inputStream];
                }

                $c = fread($inputStream, 1);

                // as opposed to fgets(), fread() returns an empty string when the stream content is empty, not false.
                if (false === $c || '' === $c) {
                    $cursor->show();
                    shell_exec('stty ' . $sttyMode);
                    throw new \Exception('Aborted.');
                } elseif ("\033" === $c) {
                    // Did we read an escape sequence?
                    $c .= fread($inputStream, 2);

                    // A = Up Arrow. B = Down Arrow
                    match ($c[2] ?? null) {
                        'A' => $selected = !$selected, // Up
                        'B' => $selected = !$selected, // Down
                        'C' => $selected = !$selected, // Left
                        'D' => $selected = !$selected, // Right
                    };

                    $this->writeConfirm($cursor, $selected);
                } elseif ($c === "\n") {
                    break;
                }
            }

            $cursor->moveUp(3);

            $cursor->clearOutput();

            $this->output->writeln($this->active('◇') . " {$question}");
            $this->output->writeln($this->dim('│ ' . ($selected ? 'Yes' : 'No')));

            $cursor->show();

            $cursor->show();

            shell_exec('stty ' . $sttyMode);

            return $selected;
        };
    }

    public function dim()
    {
        return function (string $text) {
            $this->output->getFormatter()->setStyle('unfocused', new OutputFormatterStyle('gray'));

            return "<unfocused>{$text}</unfocused>";
        };
    }

    public function active()
    {
        return function (string $text) {
            return "<info>{$text}</info>";
        };
    }

    public function warning()
    {
        return function (string $text) {
            if (!$this->output->getFormatter()->hasStyle('warning')) {
                $style = new OutputFormatterStyle('yellow');

                $this->output->getFormatter()->setStyle('warning', $style);
            }

            return "<warning>{$text}</warning>";
        };
    }

    public function interactiveAsk()
    {
        return function (string $question, string $default = null, $validator = null, string $errorMessage = null) {
            $styleMethod = $errorMessage ? 'warning' : 'active';

            $this->output->writeln($this->dim('│'));
            $this->output->writeln($this->{$styleMethod}($errorMessage ? '▲' : '◆') . " {$question}");
            $this->output->writeln($this->{$styleMethod}('│ '));
            $this->output->writeln($this->{$styleMethod}('└ ' . $errorMessage ?? ''));

            $inputStream = fopen('php://stdin', 'r');

            // TODO: deal with default value

            $cursor = new Cursor($this->output, $inputStream);

            // Position the cursor so they can start typing in the correct place
            $cursor->moveUp(2);
            $cursor->moveRight(2);

            $ret = fgets($inputStream, 4096);

            if ($validator) {
                try {
                    Validator::make(['value' => $ret], ['value' => $validator])->validate();
                } catch (ValidationException $e) {
                    $cursor->moveUp(3);
                    $cursor->clearOutput();

                    return $this->interactiveAsk($question, $default, $validator, $e->getMessage());
                }
            }

            $cursor->moveUp(3);

            $cursor->clearOutput();

            $this->output->writeln($this->dim('│'));
            $this->output->writeln($this->active('◇') . " {$question}");
            $this->output->writeln($this->dim('│ ' . $ret));

            // Move the cursor up for the next item so there isn't a gap
            $cursor->moveUp();

            return $ret;
        };
    }

    public function writeChoices()
    {
        return function (
            Cursor $cursor,
            Collection $items,
            int $index,
            Collection $selected,
            bool $multiple,
            string $errorMessage = null,
            bool $firstTime = false
        ) {
            if (!$firstTime) {
                $cursor->moveUp($items->count() + 1);
            }

            $styleMethod = $errorMessage ? 'warning' : 'active';

            $items->each(function ($item, $i) use ($index, $selected, $multiple, $styleMethod) {
                $tag = $index === $i ? 'focused' : 'unfocused';
                $radioTag = $selected->contains($i) ? 'radio_selected' : 'radio_unselected';
                if ($multiple) {
                    $checked = $selected->contains($i) ? '■' : '□';
                } else {
                    $checked = $selected->contains($i) ? '●' : '○';
                }

                $this->output->writeln($this->{$styleMethod}('│') . " <{$radioTag}>{$checked}</{$radioTag}> <{$tag}>{$item}</{$tag}>");
            });

            $this->output->writeln($this->{$styleMethod}('└ ') . $errorMessage ?? '');
        };
    }

    public function writeConfirm()
    {
        return function (Cursor $cursor, $selected, $firstTime = false) {
            if (!$firstTime) {
                $cursor->moveUp(2);
            }

            $index = $selected ? 0 : 1;

            $result = collect(['Yes', 'No'])->map(function ($item, $i) use ($index) {
                $tag = $index === $i ? 'focused' : 'unfocused';
                $radioTag = $index === $i ? 'radio_selected' : 'radio_unselected';
                $checked = $index === $i ? '●' : '○';

                return "<{$radioTag}>{$checked}</{$radioTag}> <{$tag}>{$item}</{$tag}>";
            });

            $this->output->writeln($this->active('│') . ' ' . $result->join(' / '));

            $this->output->writeln($this->active('└'));
        };
    }

    public function intro()
    {
        return function (string $text) {
            $this->output->getFormatter()->setStyle('intro', new OutputFormatterStyle('black', 'green'));

            $this->output->newLine();
            $this->output->writeln($this->dim('┌') . " <intro> {$text} </intro>");
        };
    }

    public function outro()
    {
        return function (string $text) {
            $this->output->writeln($this->dim('│'));
            $this->output->writeln($this->dim('└') . " {$text}");
            $this->output->newLine();
        };
    }
}
