<?php

declare(strict_types=1);

namespace Keboola\ManageApi\Auth;

interface AuthenticationStrategyInterface
{
    /**
     * @return array<string, string>
     */
    public function getAuthenticationHeaders(): array;
}
