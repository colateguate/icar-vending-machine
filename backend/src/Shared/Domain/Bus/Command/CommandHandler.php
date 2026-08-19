<?php

declare(strict_types=1);

namespace App\Shared\Domain\Bus\Command;

/**
 * Marks a class as the one place a given command is carried out.
 *
 * A marker rather than a method signature, because handlers are invokable and
 * each one types its own command. Its real job is registration: the container
 * tags every implementation onto the command bus, which is what keeps
 * #[AsMessageHandler] — and with it the framework — out of the application
 * layer entirely.
 */
interface CommandHandler
{
}
