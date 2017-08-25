<?php
namespace App\Async;

use Formapro\Pvm\Enqueue\HandleAsyncTransitionProcessor;

final class Commands
{
    const CREATE_JOB = 'jm_create_job';

    const CREATE_SUB_JOBS = 'create_sub_jobs';

    const SCHEDULE_JOB = 'schedule_job';

    const EXECUTE_PROCESS = 'execute_process';

    const JOB_RESULT = 'job_result';

    const PVM_HANDLE_ASYNC_TRANSITION = HandleAsyncTransitionProcessor::COMMAND;
}
