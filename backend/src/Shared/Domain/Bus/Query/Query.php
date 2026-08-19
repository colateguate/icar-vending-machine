<?php

declare(strict_types=1);

namespace App\Shared\Domain\Bus\Query;

/**
 * A question about the current state, which never changes it.
 *
 * The type parameter records the shape of the answer, so asking a question
 * returns something the analyser can check rather than an anonymous value the
 * caller has to guess at.
 *
 * @template-covariant TResponse
 */
interface Query
{
}
