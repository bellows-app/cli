<?php

namespace Bellows\Util;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class FileHelper
{
    public function __construct(protected string $path)
    {
        if (Str::endsWith($path, ['.js', '.ts'])) {
            // Automatically look for js and ts version, whichever exists first
            $paths = [
                $path,
                Str::endsWith($path, '.js')
                    ? Str::replaceLast('.js', '.ts', $path)
                    : Str::replaceLast('.ts', '.js', $path),
            ];

            foreach ($paths as $path) {
                if (File::exists($path)) {
                    $this->path = $path;
                    break;
                }
            }
        }
    }

    public function addJsImport(string $content): static
    {
        $vite = $this->getFile();

        if (Str::contains($vite, $content)) {
            return $this;
        }

        $content = Str::finish($content, ';');

        $lines = collect(explode(PHP_EOL, $vite));
        $lastImport = $lines->reverse()->search(fn ($l) => Str::startsWith($l, 'import'));

        if ($lastImport === false) {
            $lines->prepend($content);
        } else {
            $lines->splice($lastImport + 1, 0, $content);
        }

        $this->writeFile($lines->implode(PHP_EOL));

        return $this;
    }

    public function replace(string $search, string $replace): static
    {
        $this->writeFile(str_replace($search, $replace, $this->getFile()));

        return $this;
    }

    protected function getFile(): string
    {
        return File::get($this->path);
    }

    protected function writeFile(string $contents): void
    {
        File::put($this->path, $contents);
    }
}
