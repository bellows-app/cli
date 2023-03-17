<?php

namespace Bellows\Commands;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Process;
use LaravelZero\Framework\Commands\Command;

class Support extends Command
{
    protected $signature = 'support';

    protected $description = 'Submit a support request, feedback, bug, or view the docs.';

    public function handle()
    {
        $type = $this->choice('What can we help you with?', ['Bug', 'Feedback', 'Support', 'View the Docs']);

        if ($type === 'View the Docs') {
            if ($this->confirm('This will open the docs in your browser, continue?', true)) {
                Process::run('open https://bellows.dev/docs');
            }

            return;
        }

        $prompt = [
            'Bug'      => 'Please describe the bug in detail',
            'Feedback' => 'Love to hear it! What would you like us to know',
            'Support'  => 'Got it, what can we help you with',
        ];

        $description = $this->askRequired($prompt[$type], null, true);

        $email = $this->ask('What is your email address?');

        $params = [
            'type'        => $type,
            'description' => $description,
            'email'       => $email,
        ];

        ray(
            json_encode($params),
            env('WEBHOOK_SIGNING_SECRET', 'LxNu2n6j4PfHuczaHhLd'),
        );

        $computedSignature = hash_hmac(
            'sha256',
            json_encode($params),
            env('WEBHOOK_SIGNING_SECRET', 'LxNu2n6j4PfHuczaHhLd'),
        );

        $repsonse = Http::withHeaders([
            'Signature' => $computedSignature,
        ])->asJson()->acceptJson()->throw()->baseUrl(env('BELLOWS_URL', 'https://bellows.dev'))->post('cli/support', $params);

        ray($computedSignature, json_encode($params));
        // dd($computedSignature, $repsonse);

        $this->info('Thank you for your support request. We will get back to you as soon as possible.');
    }
}
