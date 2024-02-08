<?php

namespace Bellows\Commands;

use Illuminate\Support\Facades\Process;
use LaravelZero\Framework\Commands\Command;

class Support extends Command
{
    protected $signature = 'support';

    protected $description = 'Submit a support request, feedback, bug, or view the docs.';

    public function handle()
    {
        $type = $this->choice('What can we help you with?', ['File an Issue', 'View the Docs']);

        if ($type === 'View the Docs') {
            if ($this->confirm('This will open the docs in your browser, continue?', true)) {
                Process::run(sprintf('open %s/docs', config('app.url')));
            }

            return;
        }

        $this->info('To file an issue, please visit: https://github.com/bellows-app/cli/issues');
    }
}
