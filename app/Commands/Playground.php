<?php

namespace Bellows\Commands;

use LaravelZero\Framework\Commands\Command;

class Playground extends Command
{
    protected $signature = 'playground';

    protected $description = 'Go ahead. Play around.';

    public function handle()
    {
        $this->intro('Hey-o! Welcome to my CLI app.');

        $freestyle = $this->interactiveAsk(
            question: 'Tell me something interesting:',
            validator: ['required', 'numeric'],
        );

        $thing = $this->interactiveChoice(
            question: 'Pick a thing, any thing:',
            items: ['first thing', 'second thing', 'third thing'],
            validator: ['required'],
        );

        $things = $this->interactiveChoice(
            question: 'Pick a couple of things:',
            items: ['red thing', 'blue thing', 'green thing'],
            multiple: true,
            validator: ['required'],
        );

        $confirmed = $this->interactiveConfirm(
            question: 'You sure?',
        );

        $this->outro("That's all folks!");
    }
}
