<?php

declare(strict_types=1);

namespace Keboola\ManageApi\Auth;

final readonly class ManageApiTokenAuthenticationStrategy implements AuthenticationStrategyInterface
{
    public function __construct(private string $token)
    {
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
