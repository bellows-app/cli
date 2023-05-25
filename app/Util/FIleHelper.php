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

    public function addJsImport(string|array $content): static
    {
        $content = collect(is_array($content) ? $content : [$content])->map(
            fn ($line) => strlen(trim($line)) ? Str::finish($line, ';') : $line
        )->implode(PHP_EOL);

        $fileContents = $this->getFile();

        if (Str::contains($fileContents, $content)) {
            return $this;
        }

        $lines = collect(explode(PHP_EOL, $fileContents));
        $lastImport = $lines->reverse()->search(fn ($l) => Str::startsWith($l, 'import'));

        if ($lastImport === false) {
            $lines->prepend($content);
        } else {
            $lines->splice($lastImport + 1, 0, $content);
        }

        $this->writeFile($lines->implode(PHP_EOL));

        return $this;
    }

    public function addAfterJsImports(string|array $content)
    {
        if (!is_array($content)) {
            $content = [$content];
        }

        // Just add a line between the last import and the new content
        array_unshift($content, PHP_EOL);

        // We're effectively doing the same thing
        return $this->addJsImport($content);
    }

    public function replace(string $search, string $replace): static
    {
        // TODO: Handle missing files more gracefully
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
