<?php

namespace Tests\Feature;

use Illuminate\Console\Scheduling\CallbackEvent;
use Illuminate\Console\Scheduling\Schedule;
use Tests\TestCase;

/** The server's PHP has proc_open disabled: every scheduled task must run in-process (job or call), never as a command. */
class ScheduleInProcessTest extends TestCase
{
    public function test_no_scheduled_task_starts_a_child_process(): void
    {
        $events = app(Schedule::class)->events();
        $this->assertNotEmpty($events);
        foreach ($events as $event) {
            $this->assertInstanceOf(CallbackEvent::class, $event, 'Scheduled command needs proc_open: '.$event->command);
        }
        $this->assertContains('platform-health', array_map(fn ($e) => $e->description, $events));
    }
}
