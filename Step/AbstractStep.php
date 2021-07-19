<?php

namespace Oro\Bundle\BatchBundle\Step;

use Oro\Bundle\BatchBundle\Entity\StepExecution;
use Oro\Bundle\BatchBundle\Event\EventInterface;
use Oro\Bundle\BatchBundle\Event\InvalidItemEvent;
use Oro\Bundle\BatchBundle\Event\StepExecutionEvent;
use Oro\Bundle\BatchBundle\Exception\JobInterruptedException;
use Oro\Bundle\BatchBundle\Job\BatchStatus;
use Oro\Bundle\BatchBundle\Job\ExitStatus;
use Oro\Bundle\BatchBundle\Job\JobRepositoryInterface;
use Symfony\Contracts\EventDispatcher\Event;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * A Step implementation that provides common behavior to subclasses, including registering and calling
 * listeners.
 *
 * Inspired by Spring Batch org.springframework.batch.core.step.AbstractStep;
 *
 * @author    Benoit Jacquemont <benoit@akeneo.com>
 * @copyright 2013 Akeneo SAS (http://www.akeneo.com)
 * @license   http://opensource.org/licenses/MIT MIT
 */
abstract class AbstractStep implements StepInterface
{
    protected string $name;

    protected ?EventDispatcherInterface $eventDispatcher = null;

    protected ?JobRepositoryInterface $jobRepository = null;

    public function __construct(string $name)
    {
        $this->name = $name;
    }

    public function setEventDispatcher(EventDispatcherInterface $eventDispatcher): self
    {
        $this->eventDispatcher = $eventDispatcher;

        return $this;
    }

    public function setJobRepository(JobRepositoryInterface $jobRepository): self
    {
        $this->jobRepository = $jobRepository;

        return $this;
    }

    public function getJobRepository(): ?JobRepositoryInterface
    {
        return $this->jobRepository;
    }

    /**
     * {@inheritdoc}
     */
    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = $name;

        return $this;
    }

    /**
     * Extension point for subclasses to execute business logic. Subclasses should set the {@link ExitStatus} on the
     * {@link StepExecution} before returning.
     *
     * @param StepExecution $stepExecution the current step context
     *
     * @throws \Exception
     */
    abstract protected function doExecute(StepExecution $stepExecution);

    /**
     * {@inheritdoc}
     */
    public function getConfigurableStepElements(): array
    {
        return [];
    }

    final public function execute(StepExecution $stepExecution): void
    {
        $this->dispatchStepExecutionEvent(EventInterface::BEFORE_STEP_EXECUTION, $stepExecution);

        $stepExecution->setStartTime(new \DateTime());
        $stepExecution->setStatus(new BatchStatus(BatchStatus::STARTED));
        $this->jobRepository->updateStepExecution($stepExecution);

        // Start with a default value that will be trumped by anything
        $exitStatus = new ExitStatus(ExitStatus::EXECUTING);

        try {
            $this->doExecute($stepExecution);

            $exitStatus = new ExitStatus(ExitStatus::COMPLETED);
            $exitStatus->logicalAnd($stepExecution->getExitStatus());

            $this->jobRepository->updateStepExecution($stepExecution);

            // Check if someone is trying to stop us
            if ($stepExecution->isTerminateOnly()) {
                throw new JobInterruptedException("JobExecution interrupted.");
            }

            // Need to upgrade here not set, in case the execution was stopped
            $stepExecution->upgradeStatus(BatchStatus::COMPLETED);
            $this->dispatchStepExecutionEvent(EventInterface::STEP_EXECUTION_SUCCEEDED, $stepExecution);
        } catch (\Exception $e) {
            $stepExecution->upgradeStatus(self::determineBatchStatus($e));

            $exitStatus = $exitStatus->logicalAnd($this->getDefaultExitStatusForFailure($e));

            $stepExecution->addFailureException($e);
            $this->jobRepository->updateStepExecution($stepExecution);

            if ($stepExecution->getStatus()->getValue() === BatchStatus::STOPPED) {
                $this->dispatchStepExecutionEvent(EventInterface::STEP_EXECUTION_INTERRUPTED, $stepExecution);
            } else {
                $this->dispatchStepExecutionEvent(EventInterface::STEP_EXECUTION_ERRORED, $stepExecution);
            }
        }

        $this->dispatchStepExecutionEvent(EventInterface::STEP_EXECUTION_COMPLETED, $stepExecution);

        $stepExecution->setEndTime(new \DateTime());
        $stepExecution->setExitStatus($exitStatus);
        $this->jobRepository->updateStepExecution($stepExecution);
    }

    /**
     * Determine the step status based on the exception.
     */
    private static function determineBatchStatus(\Exception $e): int
    {
        if ($e instanceof JobInterruptedException || $e->getPrevious() instanceof JobInterruptedException) {
            return BatchStatus::STOPPED;
        }

        return BatchStatus::FAILED;
    }

    /**
     * Default mapping from throwable to {@link ExitStatus}. Clients can modify the exit code using a
     * {@link StepExecutionListener}.
     *
     * @param \Exception $e the cause of the failure
     *
     * @return ExitStatus {@link ExitStatus}
     */
    private function getDefaultExitStatusForFailure(\Exception $e): ExitStatus
    {
        if ($e instanceof JobInterruptedException || $e->getPrevious() instanceof JobInterruptedException) {
            $exitStatus = new ExitStatus(ExitStatus::STOPPED);
            $exitStatus->addExitDescription(JobInterruptedException::class);
        } else {
            $exitStatus = new ExitStatus(ExitStatus::FAILED);
            $exitStatus->addExitDescription($e);
        }

        return $exitStatus;
    }

    protected function dispatchStepExecutionEvent(string $eventName, StepExecution $stepExecution): void
    {
        $event = new StepExecutionEvent($stepExecution);
        $this->dispatch($eventName, $event);
    }

    protected function dispatchInvalidItemEvent(
        string $class,
        string $reason,
        array $reasonParameters,
        array $item
    ): void {
        $event = new InvalidItemEvent($class, $reason, $reasonParameters, $item);
        $this->dispatch(EventInterface::INVALID_ITEM, $event);
    }

    protected function dispatch(string $eventName, Event $event): void
    {
        if ($this->eventDispatcher) {
            $this->eventDispatcher->dispatch($event, $eventName);
        }
    }
}
