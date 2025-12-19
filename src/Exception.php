<?php

declare(strict_types=1);

namespace Keboola\ManageApi;

use Exception as GenericException;

class Exception extends GenericException
{
    protected $stringCode;

    protected $contextParams;

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


    public function getStringCode()
    {
        return $this->stringCode;
    }

    /**
     * @param $stringCode
     * @return Exception
     */
    public function setStringCode($stringCode): static
    {
        $this->stringCode = $stringCode ? (string) $stringCode : 'APPLICATION_ERROR';
        return $this;
    }

    public function getContextParams()
    {
        return $this->contextParams;
    }

    /**
     * @param array $contextParams
     * @return Exception
     */
    public function setContextParams($contextParams): static
    {
        $this->contextParams = (array) $contextParams;
        return $this;
    }
}
