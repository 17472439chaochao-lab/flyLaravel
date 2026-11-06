<?php

declare(strict_types=1);

namespace Readfly\Laravel\Modules;

use Readfly\Laravel\Support\Code;
use Readfly\Laravel\Support\ResponsePayload;

class ServiceModule
{
    protected ResponsePayload $response;

    public function __construct()
    {
        $this->response = ResponsePayload::error(Code::LOGIC_ERROR, 'LOGIC ERROR');
    }

    public function getResult(array $mergeData = [], mixed $default = []): mixed
    {
        $data = $this->response->data;

        if (! is_array($data)) {
            return $data ?? $default;
        }

        if ($mergeData === []) {
            return $data !== [] ? $data : $default;
        }

        return array_merge($mergeData, $data);
    }

    public function getResponse(): ResponsePayload
    {
        return $this->response;
    }

    public function getState(): bool
    {
        return $this->response->state;
    }

    public function getMessage(): string
    {
        return $this->response->message;
    }

    public function getCode(): int
    {
        return $this->response->code;
    }

    public function getExtras(): array
    {
        return $this->response->extras;
    }

    public function toArray(): array
    {
        return $this->response->toArray();
    }

    protected function success(mixed $result = [], array $extras = [], string $message = '处理成功'): bool
    {
        $this->response = ResponsePayload::success($result, $message, $extras);

        return true;
    }

    protected function fail(int $code = Code::ERROR, string $message = '', mixed $data = [], array $extras = []): bool
    {
        $this->response = ResponsePayload::error($code, $message, $data, $extras);

        return false;
    }

    protected function failWithValidations(array $validations, string $message = ''): bool
    {
        return $this->fail(Code::VALIDATE_FAILED, $message !== '' ? $message : Code::message(Code::VALIDATE_FAILED), [], [
            'validations' => $validations,
        ]);
    }
}