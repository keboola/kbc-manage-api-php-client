<?php

declare(strict_types=1);

namespace Keboola\ManageApi\Auth;

use InvalidArgumentException;
use RuntimeException;

final readonly class KubernetesServiceAccountTokenAuthenticationStrategy implements AuthenticationStrategyInterface
{
    public function __construct(private string $tokenPath)
    {
        if (trim($tokenPath) === '') {
            throw new InvalidArgumentException('Kubernetes service account token path must not be empty');
        }
    }

    /**
     * @return array<string, string>
     */
    public function getAuthenticationHeaders(): array
    {
        if (!is_readable($this->tokenPath)) {
            throw new RuntimeException(sprintf(
                'Kubernetes service account token file "%s" is not readable',
                $this->tokenPath,
            ));
        }

        $token = file_get_contents($this->tokenPath);
        if ($token === false) {
            throw new RuntimeException(sprintf(
                'Failed to read Kubernetes service account token file "%s"',
                $this->tokenPath,
            ));
        }

        $token = trim($token);
        if ($token === '') {
            throw new RuntimeException(sprintf(
                'Kubernetes service account token file is empty: "%s"',
                $this->tokenPath,
            ));
        }

        return [
            'X-Kubernetes-Authorization' => 'Bearer ' . $token,
        ];
    }
}
