<?php

declare(strict_types=1);

namespace Keboola\ManageApi;

/**
 * Thrown by {@see Client::payAsYouGoTopUpTry()} when an automatic top-up for the project is already
 * in progress (the Manage API responds with HTTP 429 and the string code `topUp.alreadyInProgress`).
 *
 * Extends {@see ClientException} so callers that do not care about the distinction can keep catching
 * the generic exception.
 */
class PayAsYouGoTopUpLockedException extends ClientException
{
}
