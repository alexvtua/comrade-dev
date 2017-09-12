<?php
namespace DemoApp;

use App\Async\Commands;
use App\Async\RunSubJobsResult;
use App\Async\RunJob;
use App\Infra\Uuid;
use App\Infra\Yadm\ObjectBuilderHook;
use App\JobStatus;
use App\Model\Job;
use App\Async\JobResult as JobResultMessage;
use App\Model\JobResult;
use App\Model\JobResultMetrics;
use App\Model\JobTemplate;
use App\Model\QueueRunner;
use App\Model\SubJobTemplate;
use App\CollectMetrics;
use App\Model\Throwable;
use Enqueue\Consumption\ChainExtension;
use Enqueue\Consumption\Extension\LoggerExtension;
use Enqueue\Consumption\Extension\SignalExtension;
use Enqueue\Consumption\QueueConsumer;
use Enqueue\Consumption\Result;
use function Enqueue\dsn_to_context;
use Interop\Queue\PsrContext;
use Interop\Queue\PsrMessage;
use Enqueue\Util\JSON;
use function Makasim\Values\get_value;
use function Makasim\Values\get_values;
use function Makasim\Values\register_cast_hooks;
use function Makasim\Values\register_object_hooks;
use Symfony\Component\Console\Logger\ConsoleLogger;
use Symfony\Component\Console\Output\ConsoleOutput;

require_once __DIR__.'/../vendor/autoload.php';


$output = new ConsoleOutput(ConsoleOutput::VERBOSITY_DEBUG);
$logger = new ConsoleLogger($output);

register_cast_hooks();
register_object_hooks();

(new ObjectBuilderHook([
    Job::SCHEMA => Job::class,
    JobResult::SCHEMA => JobResult::class,
    JobResultMetrics::SCHEMA => JobResultMetrics::class,
    RunJob::SCHEMA => RunJob::class,
    JobResultMessage::SCHEMA => JobResultMessage::class,
    RunSubJobsResult::SCHEMA => RunSubJobsResult::class,
    JobTemplate::SCHEMA => JobTemplate::class,
    SubJobTemplate::SCHEMA => SubJobTemplate::class,
    RunSubJobsResult::SCHEMA => RunSubJobsResult::class,
    QueueRunner::SCHEMA => QueueRunner::class,
    Throwable::SCHEMA => Throwable::class,
]))->register();

/** @var \Enqueue\AmqpExt\AmqpContext $c */
$c = dsn_to_context(getenv('ENQUEUE_DSN'));

foreach (['demo_success_job', 'demo_failed_job', 'demo_failed_with_exception_job', 'demo_success_sub_job', 'demo_run_sub_tasks', 'demo_intermediate_status', 'demo_random_job', 'demo_success_on_third_attempt'] as $queueName) {
    $q = $c->createQueue($queueName);
    $q->addFlag(AMQP_DURABLE);
    $c->declareQueue($q);
}

$queueConsumer = new QueueConsumer($c, new ChainExtension([
    new LoggerExtension($logger),
    new SignalExtension(),
]), 0, 200);

$queueConsumer->bind('demo_success_job', function(PsrMessage $message, PsrContext $context) {
    if ($message->isRedelivered()) {
        return Result::reject('The message was redelivered. Reject it');
    }

    $runJob = RunJob::create(JSON::decode($message->getBody()));

    $result = JobResult::createFor(JobStatus::STATUS_COMPLETED);

    $metrics = CollectMetrics::start();

    do_something_important(rand(2, 6));

    $metrics->stop()->updateResult($result);

    send_result(convert($runJob, $result));

    return Result::ACK;
});

$queueConsumer->bind('demo_success_on_third_attempt', function(PsrMessage $message, PsrContext $context) {
    if ($message->isRedelivered()) {
        return Result::reject('The message was redelivered. Reject it');
    }

    $runJob = RunJob::create(JSON::decode($message->getBody()));

    $result = JobResult::createFor(JobStatus::STATUS_FAILED);
    if (get_value($runJob->getJob(), 'retryAttempts') > 2) {
        $result = JobResult::createFor(JobStatus::STATUS_COMPLETED);
    }

    $metrics = CollectMetrics::start();

    do_something_important(rand(2, 6));

    $metrics->stop()->updateResult($result);

    send_result(convert($runJob, $result));

    return Result::ACK;
});

$queueConsumer->bind('demo_random_job', function(PsrMessage $message, PsrContext $context) {
    if ($message->isRedelivered()) {
        return Result::reject('The message was redelivered. Reject it');
    }

    $runJob = RunJob::create(JSON::decode($message->getBody()));

    $statuses = [JobStatus::STATUS_FAILED, JobStatus::STATUS_COMPLETED, JobStatus::STATUS_COMPLETED, JobStatus::STATUS_COMPLETED];
    $result = JobResult::createFor($statuses[rand(0, 3)]);

    $metrics = CollectMetrics::start();

    do_something_important(rand(2, 6));

    $metrics->stop()->updateResult($result);

    send_result(convert($runJob, $result));

    return Result::ACK;
});

$queueConsumer->bind('demo_run_sub_tasks', function(PsrMessage $message, PsrContext $context) {
    if ($message->isRedelivered()) {
        return Result::reject('The message was redelivered. Reject it');
    }

    $runJob = RunJob::create(JSON::decode($message->getBody()));

    $result = JobResult::createFor(JobStatus::STATUS_RUN_SUB_JOBS);

    $jobResultMessage = convert($runJob, $result);
    $values = get_values($jobResultMessage);
    unset($values['schema']);
    $jobResultMessage = RunSubJobsResult::create($values);
    $jobResultMessage->setProcessTemplateId(Uuid::generate());

    $jobTemplate = JobTemplate::create();
    $jobTemplate->setName('testSubJob1');
    $jobTemplate->setTemplateId(Uuid::generate());
    $jobTemplate->setDetails(['foo' => 'fooVal', 'bar' => 'barVal']);
    $jobTemplate->setRunner(QueueRunner::createFor('demo_random_job'));
    $jobResultMessage->addJobTemplate($jobTemplate);

    $jobTemplate = JobTemplate::create();
    $jobTemplate->setName('testSubJob2');
    $jobTemplate->setTemplateId(Uuid::generate());
    $jobTemplate->setDetails(['foo' => 'fooVal', 'bar' => 'barVal']);
    $jobTemplate->setRunner(QueueRunner::createFor('demo_random_job'));
    $jobResultMessage->addJobTemplate($jobTemplate);

    $jobTemplate = JobTemplate::create();
    $jobTemplate->setName('testSubJob3');
    $jobTemplate->setTemplateId(Uuid::generate());
    $jobTemplate->setDetails(['foo' => 'fooVal', 'bar' => 'barVal']);
    $jobTemplate->setRunner(QueueRunner::createFor('demo_random_job'));
    $jobResultMessage->addJobTemplate($jobTemplate);

    $jobTemplate = JobTemplate::create();
    $jobTemplate->setName('testSubJob4');
    $jobTemplate->setTemplateId(Uuid::generate());
    $jobTemplate->setDetails(['foo' => 'fooVal', 'bar' => 'barVal']);
    $jobTemplate->setRunner(QueueRunner::createFor('demo_random_job'));
    $jobResultMessage->addJobTemplate($jobTemplate);

    $metrics = CollectMetrics::start();

    do_something_important(rand(2, 6));

    $metrics->stop()->updateResult($result);

    send_result($jobResultMessage);

    return Result::ACK;
});

$queueConsumer->bind('demo_failed_job', function(PsrMessage $message, PsrContext $context) {
    if ($message->isRedelivered()) {
        return Result::reject('The message was redelivered. Reject it');
    }

    $runJob = RunJob::create(JSON::decode($message->getBody()));

    $result = JobResult::createFor(JobStatus::STATUS_FAILED);

    $metrics = CollectMetrics::start();

    do_something_important(rand(2, 6));

    $metrics->stop()->updateResult($result);

    send_result(convert($runJob, $result));

    return Result::ACK;
});

$queueConsumer->bind('demo_failed_with_exception_job', function(PsrMessage $message, PsrContext $context) {
    if ($message->isRedelivered()) {
        return Result::reject('The message was redelivered. Reject it');
    }

    $runJob = RunJob::create(JSON::decode($message->getBody()));

    $result = JobResult::createFor(JobStatus::STATUS_FAILED);

    $metrics = CollectMetrics::start();

    do_something_important(rand(2, 6));

    $metrics->stop()->updateResult($result);

    $result->setError(Throwable::createFromThrowable(new \LogicException('Something went wrong')));
    send_result(convert($runJob, $result));

    return Result::ACK;
});

$queueConsumer->bind('demo_intermediate_status', function(PsrMessage $message, PsrContext $context) {
    if ($message->isRedelivered()) {
        return Result::reject('The message was redelivered. Reject it');
    }

    $runJob = RunJob::create(JSON::decode($message->getBody()));
    $result = JobResult::createFor(JobStatus::STATUS_RUNNING);

    $metrics = CollectMetrics::start();

    do_something_important(rand(2, 6));

    send_result(convert($runJob, $result));

    do_something_important(rand(2, 6));

    $metrics->stop()->updateResult($result);

    $result = JobResult::createFor(JobStatus::STATUS_COMPLETED);
    send_result(convert($runJob, $result));

    return Result::ACK;
});

$queueConsumer->consume();

function send_result(JobResultMessage $message) {
    global $c;

    $c->createProducer()->send(
        $c->createQueue(Commands::JOB_RESULT),
        $c->createMessage(JSON::encode($message))
    );
}

/**
 * @param RunJob $runJob
 * @param JobResult $result
 *
 * @return JobResultMessage
 */
function convert(RunJob $runJob, JobResult $result) {
    $jobResultMessage = JobResultMessage::create();
    $jobResultMessage->setToken($runJob->getToken());
    $jobResultMessage->setJobId($runJob->getJob()->getId());
    $jobResultMessage->setResult($result);

    return $jobResultMessage;
}


function do_something_important($timeout)
{
    $limit = microtime(true) + $timeout;
    
    while (microtime(true) < $limit) {
        $arr = [];
        foreach (range(1000000, 5000000) as $index) {
            $arr[] = $index;
        }
    }
}
