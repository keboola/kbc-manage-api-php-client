<?php

declare(strict_types=1);

namespace Keboola\ManageApi;

use Exception as GenericException;

class Exception extends GenericException
{
    protected string $stringCode;

    /** @var array<mixed> */
    protected array $contextParams;

    /**
     * Construct the exception
     *
     * @param string|null $message
     * @param int|null $code
     * @param GenericException|null $previous
     * @param string|null $stringCode
     * @param mixed $params
     */
    public function __construct($message = null, $code = null, $previous = null, $stringCode = null, $params = null)
    {
        $this->setStringCode($stringCode);
        $this->setContextParams($params);
        parent::__construct($message, (int) $code, $previous);
    }


    public function getStringCode(): string
    {
        return $this->stringCode;
    }

    public function setStringCode(?string $stringCode): self
    {
        $this->stringCode = $stringCode ? (string) $stringCode : 'APPLICATION_ERROR';
        return $this;
    }

    /**
     * @return array<mixed>
     */
    public function getContextParams(): array
    {
        return $this->contextParams;
    }

    /**
     * @param array<mixed> $contextParams
     */
    public function setContextParams($contextParams): self
    {
        $this->contextParams = (array) $contextParams;
        return $this;
    }
}
