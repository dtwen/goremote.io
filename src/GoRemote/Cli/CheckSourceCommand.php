<?php
namespace GoRemote\Cli;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputArgument;

class CheckSourceCommand extends \Knp\Command\Command
{
	private $app;

	protected function configure()
	{
		$this
			->setName('check-source')
			->setDescription('Check a source')
			->addArgument(
				'source',
				InputArgument::REQUIRED,
				'Which source to check? - WeWorkRemotely, Wfh, GitHub, StackOverflow, AuthenticJobs, Jobspresso, HackerNews'
				);
	}

	protected function execute(InputInterface $input, OutputInterface $output)
	{
		$this->app = $this->getSilexApplication();

		$source = $input->getArgument('source');
		$output->writeln("Testing Source: {$source}");
		$modelFile = $this->getProjectDirectory() . '/Model/' . $source . 'Model.php';
		if (!file_exists($modelFile) || !is_readable($modelFile)) {
			$output->writeln("File ({$modelFile}) doesn't exist or isn't readable - did you use the right source?");
			return 1; // exit code of 1 is 'not successful'
		}

		$jobDuplicateCount = 0;
		require_once $modelFile;
		$className = "\\GoRemote\\Model\\{$source}Model";
		if ($source == 'HackerNews') { // This is horrible - TODO make this less horrible
			$source = new $className($this->app['db']);
		} else {
			$source = new $className();
		}
 
		foreach($source->getJobs() as $job) {
			if ($jobDuplicateCount > 1) {
				$output->writeln("Too many duplicate jobs, must be up to date (unless they're not in order) so stopping");
				break;
			}

			$job->position = preg_replace('/looking for an?/i', '', $job->position);
			$job->company->id = $job->company->insert($this->app['db']);
			$jobid = $job->insert($this->app['db']);

			if ($jobid) {
				$sourceName = $className::SOURCE_NAME;
				$output->writeln("Inserted job ({$jobid}) for {$job->position} from {$job->company->name} from {$sourceName}");

				// Only tweet if we're not in debug mode (debug for vagrant)
				if (!$this->app['debug']) {
					$job->tweet($this->app);
				}
			} else {
				$jobDuplicateCount++;
			}
		}

		return 0; // exit code of 0 is 'successful'
	}
}