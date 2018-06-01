<?php

namespace KyleArch\SqlGenerator\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;

class SqlGeneratorCommand extends Command
{
    /**
     * The name and signature of the console command.
     * @var string
     */
    protected $signature = 'migrate:sql
                        {--A|all : Export all migrations instead of only pending migrations}
                        {--B|batch= : Ask for a specific batch number}
                        {--M|migrations= : Provide the name of the migrations table (will fetch from config by default)}
                        {--P|path=database/migrations : Provide the path to your migrations directory}';

    /**
     * The console command description.
     * @var string
     */
    protected $description = 'Generate plain SQL statements to run directly in a DB admin tool from migration files, instead of running via `php artisan migrate`';

    /**
     * @var \Illuminate\Database\Migrations\Migrator|\Illuminate\Foundation\Application|mixed
     */
    protected $migrator;

    /**
     * @var string
     */
    protected $migrationsTable;

    /**
     * Create a new command instance.
     * @return void
     */
    public function __construct()
    {
        parent::__construct();

        $this->migrator = app('migrator');
    }

    /**
     * Execute the console command.
     * @return mixed
     */
    public function handle()
    {
        $this->migrationsTable = $this->option('migrations') ?: config('database.migrations');

        $this->validateCommand();

        $this->writeToDisk($this->getFiles());
    }

    /**
     * @return void
     */
    protected function validateCommand()
    {
        if ($this->option('all') && $this->option('batch')) {
            $this->error("\nYou cannot provide the `--all` option and the `--batch` option at the same time\n");
            exit;
        }
    }

    /**
     * @return array
     */
    protected function getFiles()
    {
        if ($this->option('batch')) {
            $fileCollection = $this->getBatch($this->option('batch'));
        } else {
            $files          = $this->migrator->getMigrationFiles($this->option('path'));
            $fileCollection = Collection::make($files);

            if (!$this->option('all')) {
                $ran = $this->migrator->getRepository()->getRan();

                $fileCollection = $fileCollection->reject(function ($file) use ($ran) {
                    return in_array($this->migrator->getMigrationName($file), $ran);
                });
            }
        }

        return $fileCollection->values()->all();
    }

    /**
     * @param $batch
     *
     * @return \Illuminate\Support\Collection
     */
    private function getBatch($batch)
    {
        $migrations = collect();

        if (Schema::hasTable($this->migrationsTable)) {
            $migrations = DB::table($this->migrationsTable)->whereBatch((int)$batch)->get()->pluck('migration')->transform(function ($fileName) {
                return $this->option('path') . "/{$fileName}.php";
            });
        }

        return $migrations;
    }

    /**
     * @param array $files
     *
     * @return void
     */
    protected function requireFiles(array $files)
    {
        foreach ($files as $file) {
            require_once base_path($file);
        }
    }

    /**
     * @param array $migrations
     *
     * @return void
     */
    protected function writeToDisk(array $migrations)
    {
        $count = count($migrations);

        if ($count) {
            $db   = $this->migrator->resolveConnection(null);
            $bar  = $this->output->createProgressBar($count);
            $pad  = strlen((string)$count) + 1;
            $date = date('Ymd');
            $dir  = storage_path("sql/{$date}");

            if (!File::exists($dir)) {
                File::makeDirectory($dir, 0755, true);
            }

            $this->requireFiles($migrations);

            $this->info('Writing sql files to disk...');

            foreach ($migrations as $i => $migration) {
                $fileNumber = str_pad($i + 1, $pad, '0', STR_PAD_LEFT);
                $fileName   = implode('_', array_slice(explode('_', str_replace('.php', '', $migration)), 4));
                $className  = studly_case($fileName);
                $migration  = new $className;

                $queries = array_column($db->pretend(function () use ($migration) { $migration->up(); }), 'query');
                file_put_contents("{$dir}/{$fileNumber}-{$fileName}.sql", implode(";\n\n", $queries) . ';');

                $bar->advance();
            }

            $bar->finish();
            $this->info("\nDone!\nFiles can be found in {$dir}");
        } else {
            $this->error('No migrations found');
        }
    }

}
