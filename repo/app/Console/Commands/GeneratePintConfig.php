<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\File;

use function Illuminate\Filesystem\join_paths;

final class GeneratePintConfig extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'pint:config {args*?}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generates laravel/pint configuration for different files';

    /**
     * The default loaded configuration.
     *
     * @var array<string, mixed>
     */
    protected $config = [];

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $path = base_path('pint.json');

        if (File::missing($path)) {
            $this->components->error('The base pint.js file is missing');

            return 1;
        }

        $this->config = File::json(base_path('pint.json'));

        File::ensureDirectoryExists(storage_path('pint'));
        File::put($this->path('.gitignore'), '*');

        $this->makeConfigRules($this->config['rules']);
        $this->makeMigrationRules($this->config['rules']);
    }

    /**
     * @param  array  $rules
     * @return void
     */
    protected function makeConfigRules(array $rules): void
    {
        Arr::forget($rules, 'numeric_literal_separator'); // Changes port numbers

        $this->save('config', $rules);
    }

    /**
     * @param  array  $rules
     * @return void
     */
    protected function makeMigrationRules(array $rules): void
    {
        Arr::forget($rules, 'numeric_literal_separator');

        // Breaks Laravel's default class syntax
        Arr::set(
            $rules,
            'braces_position.anonymous_classes_opening_brace',
            'next_line_unless_newline_at_signature_end',
        );

        $this->save('migrations', $rules);
    }

    /**
     * Save config file with specified rules and name.
     *
     * @param  string  $name
     * @param  array  $rules
     * @return void
     */
    protected function save(string $name, array $rules): void
    {
        $path = $this->path("{$name}.json");
        $config = $this->config;
        $config['rules'] = $rules;

        if (File::put($path, json_encode($config, JSON_PRETTY_PRINT))) {
            $this->components->info("Successfully saved configuration at [{$path}]");
        } else {
            $this->components->error("Error while saving config [{$path}]");
        }
    }

    /**
     * Get path to config directory.
     *
     * @param  string  $name
     * @return string
     */
    protected function path(string $name): string
    {
        return join_paths(storage_path('pint'), $name);
    }
}
