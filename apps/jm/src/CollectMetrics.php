<?php

namespace App;

use App\Model\JobResult;
use App\Model\JobResultMetrics;

class CollectMetrics
{
    /**
     * @var int
     */
    private $startTime;

    /**
     * @var int
     */
    private $startMem;

    /**
     * @var int
     */
    private $stopTime;

    /**
     * @var int
     */
    private $stopMem;

    /**
     * @var int
     */
    private $duration;

    /**
     * @var int
     */
    private $memory;

    /**
     * @var int
     */
    private $finished;

    private function __construct()
    {
        $this->startTime = (int) microtime(true) * 1000;
        $this->startMem = memory_get_usage(true);
        $this->finished = false;
    }

    public static function start(): CollectMetrics
    {
        return new static();
    }

    public function stop(): CollectMetrics
    {
        $this->stopTime = (int) microtime(true) * 1000;
        $this->stopMem = memory_get_usage(true);

        $this->duration = $this->stopTime - $this->startTime;
        $this->memory = $this->stopMem - $this->startMem;

        $this->finished = true;

        return $this;
    }

    public function getStartTime(): int
    {
        return $this->startTime;
    }

    public function getStopTime(): int
    {
        return $this->stopTime;
    }

    public function getDuration(): int
    {
        if (false == $this->finished) {
            throw new \LogicException('Is not finished yet');
        }

        return $this->duration;
    }

    public function getMemory(): int
    {
        if (false == $this->finished) {
            throw new \LogicException('Is not finished yet');
        }

        return $this->memory;
    }

    public function updateResult(JobResult $result): void
    {
        $metrics = JobResultMetrics::create();
        $metrics->setStartTime($this->getStartTime());
        $metrics->setStopTime($this->getStopTime());
        $metrics->setDuration($this->getDuration());
        $metrics->setMemory($this->getMemory());

        $result->setMetrics($metrics);
    }
}
