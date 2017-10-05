<?php
namespace App\Pvm\Behavior;

use App\Commands;
use App\Model\PvmToken;
use App\Topics;
use App\JobStatus;
use App\Model\JobResult;
use App\Service\CreateProcessForSubJobsService;
use App\Storage\JobStorage;
use App\Storage\ProcessStorage;
use Comrade\Shared\Model\Job;
use Enqueue\Client\ProducerInterface;
use Formapro\Pvm\Behavior;
use Formapro\Pvm\Exception\WaitExecutionException;
use Formapro\Pvm\SignalBehavior;
use Formapro\Pvm\Token;
use function Makasim\Values\get_values;

class RunSubJobsProcessBehavior implements Behavior, SignalBehavior
{
    /**
     * @var JobStorage
     */
    private $jobStorage;

    /**
     * @var ProducerInterface
     */
    private $producer;

    /**
     * @var ProcessStorage
     */
    private $processStorage;

    /**
     * @var CreateProcessForSubJobsService
     */
    private $createProcessForSubJobsService;

    /**
     * @param JobStorage $jobStorage
     * @param ProcessStorage $processStorage
     * @param CreateProcessForSubJobsService $createProcessForSubJobsService
     * @param ProducerInterface $producer
     */
    public function __construct(
        JobStorage $jobStorage,
        ProcessStorage $processStorage,
        ProducerInterface $producer,
        CreateProcessForSubJobsService $createProcessForSubJobsService
    ) {
        $this->jobStorage = $jobStorage;
        $this->processStorage = $processStorage;
        $this->producer = $producer;
        $this->createProcessForSubJobsService = $createProcessForSubJobsService;
    }

    /**
     * @param PvmToken $token
     *
     * {@inheritdoc}
     */
    public function execute(Token $token)
    {
        $job = $this->jobStorage->getOneById($token->getJobId());

        if (false == $job->getCurrentResult()->isRunSubJobs()) {
            throw new \LogicException('The process comes to this task but its status is not "run_sub_jobs"');
        }

        $jobResult = JobResult::createFor(JobStatus::STATUS_RUNNING_SUB_JOBS);
        $job->addResult($jobResult);
        $job->setCurrentResult($jobResult);
        $this->jobStorage->update($job);
        $this->producer->sendEvent(Topics::JOB_UPDATED, get_values($job));

        /** @var Job[] $subJobs */
        $subJobs = $this->jobStorage->find(['parentId' => $job->getId()]);
        $process = $this->createProcessForSubJobsService->createProcess($token, $subJobs);
        $this->processStorage->insert($process);

        $this->producer->sendCommand(Commands::EXECUTE_PROCESS, ['processTemplateId' => $process->getId()]);

        throw new WaitExecutionException();
    }

    /**
     * @param PvmToken $token
     *
     * {@inheritdoc}
     */
    public function signal(Token $token)
    {
        return $this->jobStorage->lockByJobId($token->getJobId(), function(Job $job) {
            if ($job->getRunSubJobsPolicy()->isMarkParentJobAsFailed()) {
                foreach ($this->jobStorage->findSubJobs($job->getId()) as $subJob) {
                    if ($subJob->getCurrentResult()->isFailed()) {
                        $jobResult = JobResult::createFor(JobStatus::FAILED);
                        $job->addResult($jobResult);
                        $job->setCurrentResult($jobResult);

                        $this->jobStorage->update($job);
                        $this->producer->sendEvent(Topics::JOB_UPDATED, get_values($job));

                        return ['failed'];
                    }
                }
            }

            $jobResult = JobResult::createFor(JobStatus::COMPLETED);
            $job->addResult($jobResult);
            $job->setCurrentResult($jobResult);

            $this->jobStorage->update($job);
            $this->producer->sendEvent(Topics::JOB_UPDATED, get_values($job));

            return ['completed'];
        });
    }
}
