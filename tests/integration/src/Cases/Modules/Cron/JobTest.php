<?php
/**
 * @package BC Security
 */

namespace BlueChip\Security\Tests\Integration\Cases\Modules\Cron;

use BlueChip\Security\Modules\Cron;

class JobTest extends \BlueChip\Security\Tests\Integration\TestCase
{
    const HOOK = 'test-job';

    public function testScheduling()
    {
        // Create new job...
        $job = new Cron\Job(self::HOOK, time(), 'daily');

        // Constructing new job should not schedule it!
        $this->assertFalse($job->isScheduled());
        $this->assertFalse(wp_next_scheduled(self::HOOK));

        // ...schedule it...
        $job->schedule();

        // ...test...
        $this->assertTrue($job->isScheduled());
        $this->assertInternalType('int', wp_next_scheduled(self::HOOK));

        // ...and forward the job.
        return $job;
    }


    /**
     * @depends testScheduling
     */
    public function testUnscheduling(Cron\Job $job)
    {
        // Unschedule...
        $job->unschedule();

        // ...and test:
        $this->assertFalse($job->isScheduled());
        $this->assertFalse(wp_next_scheduled(self::HOOK));
    }
}
