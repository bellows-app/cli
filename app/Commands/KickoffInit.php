<?php

namespace Bellows\Commands;

use Bellows\Config\BellowsConfig;
use Bellows\Util\Editor;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use LaravelZero\Framework\Commands\Command;

class KickoffInit extends Command
{
    protected $signature = 'kickoff:init';

    protected $description = 'Initialize a new kickoff config';

    public function handle()
    {
        $name = $this->ask('Config name (for your own reference, e.g. API, Web App)');

        $file = Str::slug($name);

        $this->info("Creating config file: {$file}.json");

        $path = BellowsConfig::getInstance()->kickoffConfigPath("{$file}.json");

        if (File::exists($path)) {
            $this->error("Config file {$file}.json already exists.");

            return Command::FAILURE;
        }

        File::put($path, json_encode([
            '$schema' => './../schema.json',
            'name'    => $name,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        // TODO: Explanation and link to docs

        if ($this->confirm('Would you like to edit the config file now?', true)) {
            app(Editor::class)->open($path);
        }
    }
}
