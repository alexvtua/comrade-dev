<?php
namespace App\Service;

use App\Infra\Pvm\NotAllowedTransitionException;
use App\Storage\JobStorage;
use Comrade\Shared\Model\Job;
use Formapro\Pvm\Exception\InterruptExecutionException;
use Formapro\Pvm\Transition;

class ChangeJobStateService
{
    /**
     * @var JobStorage
     */
    private $jobStorage;

    public function __construct(JobStorage $jobStorage)
    {
        $this->jobStorage = $jobStorage;
    }

    public function can(Job $job, string $action): ?Transition
    {
        $jsm = new JobStateMachine($job);

        return $jsm->can($action);
    }

    public function change(string $jobId, string $action, callable $onChange)
    {
        return $this->jobStorage->lockByJobId($jobId, function(Job $job, JobStorage $jobStorage) use ($action, $onChange) {
            $jsm = new JobStateMachine($job);
            if (false == $transition = $jsm->can($action)) {
                throw NotAllowedTransitionException::fromNodeWithAction($job->getCurrentResult()->getStatus(), $action);
            }

            $result = call_user_func($onChange, $job, $transition);

            $jobStorage->update($job);

            return $result;
        });
    }

    public function changeInFlow(string $jobId, string $action, callable $onChange)
    {
        try {
            return $this->change($jobId, $action, $onChange);
        } catch (NotAllowedTransitionException $e) {
            throw new InterruptExecutionException($e->getMessage(), null, $e);
        }
    }
}