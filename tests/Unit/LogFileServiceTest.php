<?php

namespace Inova\NovaAdmin\Tests\Unit;

use Inova\NovaAdmin\Services\LogFileService;
use PHPUnit\Framework\TestCase;

class LogFileServiceTest extends TestCase
{
    public function test_non_laravel_log_lines_are_rendered_as_debug_entries(): void
    {
        $entries = (new LogFileService())->parseEntries("supervisor started\nworker exited\n");

        $this->assertSame([
            [
                'time'    => '',
                'level'   => 'DEBUG',
                'message' => 'supervisor started',
                'detail'  => 'supervisor started',
            ],
            [
                'time'    => '',
                'level'   => 'DEBUG',
                'message' => 'worker exited',
                'detail'  => 'worker exited',
            ],
        ], $entries);
    }

    public function test_laravel_log_entries_are_still_parsed_with_stack_detail(): void
    {
        $entries = (new LogFileService())->parseEntries(
            "[2026-06-16 10:00:00] production.ERROR: Something failed\nStack line\n"
        );

        $this->assertCount(1, $entries);
        $this->assertSame('2026-06-16 10:00:00', $entries[0]['time']);
        $this->assertSame('ERROR', $entries[0]['level']);
        $this->assertSame('Something failed', $entries[0]['message']);
        $this->assertSame("[2026-06-16 10:00:00] production.ERROR: Something failed\nStack line", $entries[0]['detail']);
    }
}
