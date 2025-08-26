<?php
namespace NicoMartin\Rest;

class RestError extends \Exception
{
	public int $statusCode;

	public function __construct(string $code = 'error', string $message = 'An error occurred', int $statusCode = 500)
	{
		parent::__construct($message, $statusCode);
		$this->code = $code;
		$this->statusCode = $statusCode;
	}

	public function errorObject()
	{
		return [
			'code' => $this->code,
			'message' => $this->message,
			'data' => [
				'status' => $this->statusCode
			]
		];
	}
}