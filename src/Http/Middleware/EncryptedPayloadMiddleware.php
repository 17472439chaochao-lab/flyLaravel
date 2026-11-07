<?php

declare(strict_types=1);

namespace Readfly\Laravel\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Readfly\Laravel\Support\Code;
use Readfly\Laravel\Support\PayloadCipher;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class EncryptedPayloadMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! PayloadCipher::enabled()) {
            return $next($request);
        }

        $header = (string) config('toolkit.payload_encryption.header', 'X-Payload-Encrypted');
        $encrypted = $request->header($header) === '1';

        if ($encrypted) {
            $payload = $request->input('payload');

            if (! is_string($payload) || $payload === '') {
                return $this->error('缺少加密 payload');
            }

            try {
                $decoded = json_decode(PayloadCipher::decrypt($payload), true, 512, JSON_THROW_ON_ERROR);
            } catch (Throwable) {
                return $this->error('Payload 解密失败');
            }

            if (! is_array($decoded)) {
                return $this->error('Payload 必须是 JSON 对象');
            }

            $request->replace($decoded);
            $request->query->replace($decoded);
            $request->json()->replace($decoded);
            $request->attributes->set('toolkit_payload_encrypted', true);
        }

        $response = $next($request);

        if (! $encrypted || ! $response instanceof JsonResponse) {
            return $response;
        }

        $payload = PayloadCipher::encrypt(json_encode($response->getData(true), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{}');

        return new JsonResponse([
            'encrypted' => true,
            'payload' => $payload,
        ], $response->getStatusCode(), $response->headers->all());
    }

    private function error(string $message): JsonResponse
    {
        return new JsonResponse([
            'state' => false,
            'code' => Code::ERROR,
            'msg' => $message,
            'data' => [],
            'extras' => [],
        ], 400);
    }
}
