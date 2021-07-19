<?php

namespace Oro\Bundle\BatchBundle\Job;

/**
 * Value object representing the status of a an Execution.
 *
 * Inspired by Spring Batch org.springframework.batch.core.BatchStatus
 *
 * @author    Benoit Jacquemont <benoit@akeneo.com>
 * @copyright 2013 Akeneo SAS (http://www.akeneo.com)
 * @license   http://opensource.org/licenses/MIT MIT
 */
class BatchStatus
{
    /**
     * The order of the status values is significant because it can be used to
     * aggregate a set of status values - the result should be the maximum
     * value. Since COMPLETED is first in the order, only if all elements of an
     * execution are COMPLETED will the aggregate status be COMPLETED. A running
     * execution is expected to move from STARTING to STARTED to COMPLETED
     * (through the order defined by {@link #upgradeTo(BatchStatus)}). Higher
     * values than STARTED signify more serious failure. ABANDONED is used for
     * steps that have finished processing, but were not successful, and where
     * they should be skipped on a restart (so FAILED is the wrong status).
     */
    public const COMPLETED = 1;
    public const STARTING = 2;
    public const STARTED = 3;
    public const STOPPING = 4;
    public const STOPPED = 5;
    public const FAILED = 6;
    public const ABANDONED = 7;
    public const UNKNOWN = 8;

    protected static array $statusLabels = [
        self::COMPLETED => 'COMPLETED',
        self::STARTING => 'STARTING',
        self::STARTED => 'STARTED',
        self::STOPPING => 'STOPPING',
        self::STOPPED => 'STOPPED',
        self::FAILED => 'FAILED',
        self::ABANDONED => 'ABANDONED',
        self::UNKNOWN => 'UNKNOWN',
    ];

    protected int $value;

    public function __construct(int $status = self::UNKNOWN)
    {
        $this->value = $status;
    }

    /**
     * Get all labels associative array
     */
    public static function getAllLabels(): array
    {
        return self::$statusLabels;
    }

    /**
     * Set the current status
     */
    public function setValue(int $value): self
    {
        $this->value = $value;

        return $this;
    }

    /**
     * Return the current status value
     */
    public function getValue(): int
    {
        return $this->value;
    }

    /**
     * Convenience method to decide if a status indicates work is starting.
     *
     * @return bool true if the status is STARTING
     */
    public function isStarting(): bool
    {
        return $this->value === self::STARTING;
    }

    /**
     * Convenience method to decide if a status indicates work is in progress.
     *
     * @return bool true if the status is STARTING, STARTED
     */
    public function isRunning(): bool
    {
        return $this->value === self::STARTING || $this->value === self::STARTED;
    }

    /**
     * Convenience method to decide if a status indicates execution was
     * unsuccessful.
     *
     * @return bool true if the status is FAILED or greater
     */
    public function isUnsuccessful(): bool
    {
        return $this->value === self::FAILED || $this->value > self::FAILED;
    }

    /**
     * Return the largest of two values
     */
    public static function max(int $value1, int $value2): int
    {
        return max($value1, $value2);
    }

    /**
     * Method used to move status values through their logical progression, and
     * override less severe failures with more severe ones. This value is
     * compared with the parameter and the one that has higher priority is
     * returned. If both are STARTED or less than the value returned is the
     * largest in the sequence STARTING, STARTED, COMPLETED. Otherwise the value
     * returned is the maximum of the two.
     *
     * @param int $otherStatus another status to compare to
     *
     * @return self with either this or the other status depending on their priority
     */
    public function upgradeTo(int $otherStatus): self
    {
        if ($this->value > self::STARTED || $otherStatus > self::STARTED) {
            $newStatus = max($this->value, $otherStatus);
        } elseif ($this->value === self::COMPLETED || $otherStatus === self::COMPLETED) {
            $newStatus = self::COMPLETED;
        } else {
            $newStatus = max($this->value, $otherStatus);
        }

        $this->value = $newStatus;

        return $this;
    }

    /**
     * Return the string representation of the current status
     */
    public function __toString(): string
    {
        return self::$statusLabels[$this->value];
    }
}
