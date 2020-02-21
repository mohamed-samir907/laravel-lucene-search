<?php namespace Nqxcode\LuceneSearch\Console;

use App;
use Config;
use File;
use Illuminate\Console\Command;
use Nqxcode\LuceneSearch\Locker\Locker;
use Nqxcode\LuceneSearch\Search;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\NullOutput;
use Queue;

class RebuildCommand extends Command
{
    protected $name = 'search:rebuild';
    protected $description = 'Rebuild the search index';

    /**
     * @var Search
     */
    private $search;

    protected function getOptions()
    {
        return [
            ['--force', null, InputOption::VALUE_NONE, 'Rebuild of search index with pre-cleaning', null],
        ];
    }

    public function fire()
    {
        if (!$this->option('verbose')) {
            $this->output = new NullOutput;
        }

        $lockFilePath = sys_get_temp_dir() . '/laravel-lucene-search/rebuild.lock';

        $locker = new Locker($lockFilePath);

        if ($locker->isLocked()) {
            $this->error('Rebuild is already running!');
        }

        $locker->doLocked(function () {
            if ($this->option('force')) {
                $this->forceRebuild();

            } else {
                $this->softRebuild();
            }
        });
    }

    private function rebuild()
    {
        /** @var Search $search */
        $this->search = App::make('search');

        $modelRepositories = $this->search->config()->repositories();

        if (count($modelRepositories) > 0) {
            foreach ($modelRepositories as $modelRepository) {
                $this->info('Creating index for model: "' . get_class($modelRepository) . '"');

                $count = $modelRepository->count();

                if ($count === 0) {
                    $this->comment(' No available models found.');
                    continue;
                }

                $chunkCount = Config::get('laravel-lucene-search::chunk');
                $progress = new ProgressBar($this->getOutput(), $count / $chunkCount);
                $progress->start();

                $modelRepository->chunk($chunkCount, function ($chunk) use ($progress) {
                    $queue = Config::get('laravel-lucene-search::queue');
                    if ($queue) {
                        Queue::push(
                            'Nqxcode\LuceneSearch\Job\MassUpdateSearchIndex',
                            [
                                'modelClass' => get_class($chunk[0]),
                                'modelKeys' => $chunk->lists($chunk[0]->getKeyName()),
                                'indexPath' => Config::get('laravel-lucene-search::index.path'),
                            ],
                            $queue);

                    } else {
                        foreach ($chunk as $model) {
                            $this->search->update($model);
                        }
                    }

                    $progress->advance();
                });

                $progress->finish();
                $this->info(PHP_EOL);
            }
            $this->info(PHP_EOL . 'Operation is fully complete!');
        } else {
            $this->error('No models found in config.php file..');
        }
    }

    private function softRebuild()
    {
        $oldIndexPath = Config::get('laravel-lucene-search::index.path');
        $newIndexPath = sys_get_temp_dir() . '/laravel-lucene-search/' . uniqid('index-', true);

        Config::set('laravel-lucene-search::index.path', $newIndexPath);

        $this->rebuild();

        $this->search->destroyConnection();

        File::cleanDirectory($oldIndexPath);
        File::copyDirectory($newIndexPath, $oldIndexPath);
        File::cleanDirectory($newIndexPath);

        Config::set('laravel-lucene-search::index.path', $oldIndexPath);

    }

    private function forceRebuild()
    {
        $this->call('search:clear');
        $this->rebuild();
    }
}
