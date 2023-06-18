<?php

namespace Bellows\Util;

use Bellows\Config;
use Bellows\PluginSdk\Facades\Console;
use Illuminate\Support\Facades\Process;

class Editor
{
    const AVAILABLE_EDITORS = [
        'atom'                   => 'atom://core/open/file?filename={file}',
        'emacs'                  => 'emacs://open?url=file://{file}',
        'idea'                   => 'idea://open?file={file}',
        'macvim'                 => 'mvim://open/?url=file://{file}',
        'netbeans'               => 'netbeans://open/?f={file}',
        'nova'                   => 'nova://core/open/file?filename={file}',
        'phpstorm'               => 'phpstorm://open?file={file}',
        'sublime'                => 'subl://open?url=file://{file}',
        'textmate'               => 'txmt://open?url=file://{file}',
        'vscode'                 => 'vscode://file/{file}',
        'vscode-insiders'        => 'vscode-insiders://file/{file}',
        'vscode-insiders-remote' => 'vscode-insiders://vscode-remote/{file}',
        'vscode-remote'          => 'vscode://vscode-remote/{file}',
        'vscodium'               => 'vscodium://file/{file}',
        'xdebug'                 => 'xdebug://{file}',
    ];

    public function __construct(protected Config $config)
    {
    }

    public function open(string $path)
    {
        $editor = self::getEditor();
        $url = self::AVAILABLE_EDITORS[$editor];
        $path = str_replace('{file}', $path, $url);

        Process::run("open {$path}");
    }

    protected function getEditor()
    {
        $editor = $this->config->get('defaults.editor') ?? env('EDITOR');

        if ($editor && isset(self::AVAILABLE_EDITORS[$editor])) {
            return $editor;
        }

        $editor = Console::choice('Select editor', array_keys(self::AVAILABLE_EDITORS));

        if (Console::confirm('Set as default editor?', true)) {
            $this->config->set('defaults.editor', $editor);
        }

        return $editor;
    }
}
