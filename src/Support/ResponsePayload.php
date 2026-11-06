<?php

declare(strict_types=1);

namespace Readfly\Laravel\Support;

use JsonSerializable;

final class ResponsePayload implements JsonSerializable
{
    public function __construct(
        public readonly bool $state,
        public readonly int $code,
        public readonly string $message = '',
        public readonly mixed $data = [],
        public readonly array $extras = [],
    ) {
    }

    public static function success(mixed $data = [], string $message = '', array $extras = []): self
    {
        return new self(true, Code::SUCCESS, $message !== '' ? $message : Code::message(Code::SUCCESS), $data, $extras);
    }

    public static function error(int $code = Code::ERROR, string $message = '', mixed $data = [], array $extras = []): self
    {
        return new self(false, $code, $message !== '' ? $message : Code::message($code), $data, $extras);
    }

    public function withData(mixed $data): self
    {
        return new self($this->state, $this->code, $this->message, $data, $this->extras);
    }

    public function withExtras(array $extras): self
    {
        return new self($this->state, $this->code, $this->message, $this->data, $extras);
    }

    public function toArray(): array
    {
        return [
            'state' => $this->state,
            'code' => $this->code,
            'msg' => $this->message,
            'data' => $this->data,
            'extras' => $this->extras,
        ];
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}