<?php

declare(strict_types=1);

namespace FreshwaveOnline\Janitor\Console;

use Illuminate\Console\Command;

use function Laravel\Prompts\multiselect;

class InstallCommand extends Command
{
    protected $signature = 'janitor:install
                            {--all : Publish the config, the views and the translations}
                            {--force : Overwrite files that already exist}';

    protected $description = 'Publish the janitor config, views and translations';

    public function handle(): int
    {
        $assets = $this->option('all')
            ? ['config', 'views', 'lang']
            : $this->promptForAssets();

        if ($assets === []) {
            $this->components->warn('Nothing published.');

            return self::SUCCESS;
        }

        foreach ($assets as $asset) {
            $this->callSilently('vendor:publish', array_filter([
                '--tag' => 'janitor-'.$asset,
                '--force' => $this->option('force') ? true : null,
            ]));

            $this->components->info(sprintf('Published janitor %s.', $asset));
        }

        $this->newLine();
        $this->components->bulletList([
            'Set JANITOR_PRIMARY, JANITOR_SUPPORT_EMAIL and JANITOR_HOME_URL in your .env.',
            'Preview every page at /_janitor while in local.',
        ]);

        return self::SUCCESS;
    }

    /**
     * @return list<string>
     */
    private function promptForAssets(): array
    {
        // Deploy scripts, CI jobs and test runners have no one to answer a
        // question; publishing the config is the sensible default there rather
        // than blocking the pipeline on a prompt nobody sees.
        if (! $this->input->isInteractive() || ! $this->hasTerminal()) {
            return ['config'];
        }

        /** @var list<string> $selected */
        $selected = multiselect(
            label: 'What would you like to publish?',
            options: [
                'config' => 'Config file (config/janitor.php)',
                'views' => 'Blade views (resources/views/vendor/janitor)',
                'lang' => 'Translations (lang/vendor/janitor)',
            ],
            default: ['config'],
        );

        return $selected;
    }

    private function hasTerminal(): bool
    {
        return defined('STDIN')
            && function_exists('stream_isatty')
            && @stream_isatty(STDIN);
    }
}
