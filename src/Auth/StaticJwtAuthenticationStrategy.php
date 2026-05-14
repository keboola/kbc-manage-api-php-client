<?php

declare(strict_types=1);

namespace Keboola\ManageApi\Auth;

use InvalidArgumentException;

final readonly class StaticJwtAuthenticationStrategy implements AuthenticationStrategyInterface
{
    private string $jwtToken;

    public function __construct(string $jwtToken)
    {
        $jwtToken = trim($jwtToken);
        if ($jwtToken === '') {
            throw new InvalidArgumentException('JWT token must not be empty');
        }

        $this->jwtToken = $jwtToken;
    }

    /**
     * @return array<string, string>
     */
    public function getAuthenticationHeaders(): array
    {
        return [
            'X-Kubernetes-Authorization' => 'Bearer ' . $this->jwtToken,
        ];
    }
}
