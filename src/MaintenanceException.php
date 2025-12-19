<?php

declare(strict_types=1);

namespace Keboola\ManageApi;

class MaintenanceException extends ClientException
{

    private readonly int $retryAfter;

    public function __construct($reason, $retryAfter, $params)
    {
        $this->retryAfter = (int) $retryAfter;
        parent::__construct($reason, 503, null, 'MAINTENANCE', $params);
    }

    /**
     * @return int
     */
    public function getRetryAfter(): int
    {
        return $this->retryAfter;
    }
}
