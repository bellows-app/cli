<?php

namespace App\Bellows;

use Illuminate\Console\Concerns\InteractsWithIO;

class Console
{
    use InteractsWithIO {
        choice as protected baseChoice;
    }

    public function choice($question, array $choices, $default = null, $attempts = null, $multiple = false)
    {
        if ($default === null && count($choices) === 1) {
            $default = array_key_first($choices);
        }

        return $this->baseChoice($question, $choices, $default, $attempts, $multiple);
    }
}
