<?php

namespace Tests\Fixtures;

use Illuminate\Contracts\Queue\Job;

class FakeQueueJob implements Job
{
    public function __construct(private readonly array $payload = []) {}

    public function uuid()
    {
        return 'uuid';
    }

    public function getJobId()
    {
        return '1';
    }

    public function payload()
    {
        return $this->payload;
    }

    public function fire() {}

    public function release($delay = 0) {}

    public function isReleased()
    {
        return false;
    }

    public function delete() {}

    public function isDeleted()
    {
        return false;
    }

    public function isDeletedOrReleased()
    {
        return false;
    }

    public function attempts()
    {
        return 1;
    }

    public function hasFailed()
    {
        return false;
    }

    public function markAsFailed() {}

    public function fail($e = null) {}

    public function maxTries()
    {
        return null;
    }

    public function maxExceptions()
    {
        return null;
    }

    public function timeout()
    {
        return null;
    }

    public function retryUntil()
    {
        return null;
    }

    public function getName()
    {
        return 'FakeJob';
    }

    public function resolveName()
    {
        return 'FakeJob';
    }

    public function resolveQueuedJobClass()
    {
        return 'FakeJob';
    }

    public function getConnectionName()
    {
        return 'database';
    }

    public function getQueue()
    {
        return 'default';
    }

    public function getRawBody()
    {
        return '{}';
    }
}
