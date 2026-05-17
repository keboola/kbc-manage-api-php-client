<?php

declare(strict_types=1);

namespace Keboola\ManageApi\Auth;

use InvalidArgumentException;

final readonly class ManageApiTokenAuthenticationStrategy implements AuthenticationStrategyInterface
{
    private string $token;

    public function __construct(string $token)
    {
        $token = trim($token);
        if ($token === '') {
            throw new InvalidArgumentException('Manage API token must not be empty');
        }

        $this->token = $token;
    }

    /**
     * @return array<string, string>
     */
    public function getAuthenticationHeaders(): array
    {
        return [
            'X-KBC-ManageApiToken' => $this->token,
        ];
    }
}
