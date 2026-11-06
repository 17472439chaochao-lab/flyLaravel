<?php

declare(strict_types=1);

namespace Readfly\Laravel\Exceptions;

use Readfly\Laravel\Support\Code;
use Readfly\Laravel\Support\ResponsePayload;
use RuntimeException;
use Throwable;

class BusinessException extends RuntimeException
{
    protected mixed $data;

    protected array $extras;

    public function __construct(int $code = Code::LOGIC_ERROR, string $message = '', mixed $data = [], array $extras = [], ?Throwable $previous = null)
    {
        parent::__construct($message !== '' ? $message : Code::message($code), $code, $previous);
        $this->data = $data;
        $this->extras = $extras;
    }

    public static function make(int $code = Code::LOGIC_ERROR, string $message = '', mixed $data = [], array $extras = []): static
    {
        return new static($code, $message, $data, $extras);
    }

    public static function invalid(string $message = '参数校验失败', array $validations = []): static
    {
        return new static(Code::VALIDATE_FAILED, $message, [], ['validations' => $validations]);
    }

    public function businessCode(): int
    {
        return (int) $this->getCode();
    }

    public function data(): mixed
    {
        return $this->data;
    }

    public function extras(): array
    {
        return $this->extras;
    }

    public function toPayload(): ResponsePayload
    {
        return ResponsePayload::error($this->businessCode(), $this->getMessage(), $this->data, $this->extras);
    }
}