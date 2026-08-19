<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Domain;

use App\Tests\Support\Doubles\FakeDomainEvent;
use App\Tests\Support\Doubles\RecordingAggregate;
use PHPUnit\Framework\TestCase;

final class AggregateRootTest extends TestCase
{
    public function test_a_fresh_aggregate_has_recorded_nothing(): void
    {
        self::assertSame([], (new RecordingAggregate())->releaseEvents());
    }

    public function test_it_releases_what_it_recorded_in_order(): void
    {
        $aggregate = new RecordingAggregate();
        $first = new FakeDomainEvent();
        $second = new FakeDomainEvent();

        $aggregate->record($first);
        $aggregate->record($second);

        self::assertSame([$first, $second], $aggregate->releaseEvents());
    }

    public function test_releasing_drains_the_recorded_events(): void
    {
        $aggregate = new RecordingAggregate();
        $aggregate->record(new FakeDomainEvent());

        $aggregate->releaseEvents();

        self::assertSame(
            [],
            $aggregate->releaseEvents(),
            'events must be published once; a second reader must not see them again',
        );
    }

    public function test_recording_after_a_release_starts_a_new_batch(): void
    {
        $aggregate = new RecordingAggregate();
        $aggregate->record(new FakeDomainEvent());
        $aggregate->releaseEvents();

        $later = new FakeDomainEvent();
        $aggregate->record($later);

        self::assertSame([$later], $aggregate->releaseEvents());
    }
}
