<?php
namespace Keboola\ManageApi;

class Exception extends \Exception
{
    /**
     * @var null|\Exception
     */
    private $previous = null;

    protected $stringCode;

    protected $contextParams;

    /**
     * Construct the exception
     *
     * @param null $message
     * @param  int $code
     * @param  \Exception $previous
     * @param  string $stringCode
     * @param  mixed|array $params
     */
    public function __construct($message = NULL, $code = NULL, $previous = NULL, $stringCode = NULL, $params = NULL)
    {
        $this->setStringCode($stringCode);
        $this->setContextParams($params);
        parent::__construct($message, (int)$code, $previous);
    }


    public function getStringCode()
    {
        return $this->stringCode;
    }

    /**
     * @param $stringCode
     * @return Exception
     */
    public function setStringCode($stringCode)
    {
        if ($stringCode) {
            $this->stringCode = (string)$stringCode;
        } else {
            $this->stringCode = "APPLICATION_ERROR";
        }
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
    public function setContextParams($contextParams)
    {
        $this->contextParams = (array)$contextParams;
        return $this;
    }

}
