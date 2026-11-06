<?php

declare(strict_types=1);

namespace Readfly\Laravel\Http\Middleware;

use Closure;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Readfly\Laravel\Exceptions\BusinessException;
use Readfly\Laravel\Http\ApiResponse;
use Readfly\Laravel\Support\Code;
use Readfly\Laravel\Support\Logger;
use Readfly\Laravel\Support\RequestContext;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

class BasicLaravelMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        RequestContext::start($request);
        $requestId = RequestContext::requestId($request);

        if ($this->corsEnabled() && $request->getMethod() === 'OPTIONS') {
            return $this->decorate($this->preflight($request), $request, $requestId);
        }

        try {
            $response = $next($request);
        } catch (HttpResponseException $exception) {
            $response = $exception->getResponse();
        } catch (ValidationException $exception) {
            $response = ApiResponse::error(Code::VALIDATE_FAILED, $exception->getMessage(), [], [
                'validations' => $exception->errors(),
            ]);
        } catch (BusinessException $exception) {
            $response = ApiResponse::error(
                $exception->businessCode(),
                $exception->getMessage(),
                $exception->data(),
                $exception->extras()
            );
        } catch (ModelNotFoundException $exception) {
            $response = ApiResponse::error(Code::NOT_FOUND, $exception->getMessage());
        } catch (Throwable $exception) {
            if (! $request->expectsJson() && ! $this->forceJson()) {
                throw $exception;
            }

            $response = $this->renderThrowable($exception);
        }

        return $this->decorate($response, $request, $requestId);
    }

    protected function renderThrowable(Throwable $exception): Response
    {
        if ($exception instanceof HttpExceptionInterface) {
            $status = $exception->getStatusCode();
            $code = match ($status) {
                401 => Code::UNAUTHORIZED,
                403 => Code::FORBIDDEN,
                404 => Code::NOT_FOUND,
                405 => Code::METHOD_NOT_ALLOWED,
                422 => Code::VALIDATE_FAILED,
                429 => Code::TOO_MANY_REQUESTS,
                default => $status >= 500 ? Code::SERVER_ERROR : Code::ERROR,
            };

            return ApiResponse::error($code, $exception->getMessage());
        }

        Logger::record('middleware:exception', $exception);

        $data = [];
        if ((bool) config('app.debug', false)) {
            $data = [
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
            ];
        }

        return ApiResponse::error(Code::SERVER_ERROR, Code::message(Code::SERVER_ERROR), $data);
    }

    protected function decorate(Response $response, Request $request, string $requestId): Response
    {
        if ($this->corsEnabled()) {
            $this->applyCors($response, $request);
        }

        if ($this->requestIdEnabled()) {
            $response->headers->set(
                (string) config('toolkit.middleware.request_id.header', 'X-Request-Id'),
                $requestId
            );
        }

        $response->headers->set('X-Response-Time', RequestContext::elapsedMs($request).'ms');

        return $response;
    }

    protected function preflight(Request $request): Response
    {
        $response = response()->noContent(204);
        $this->applyCors($response, $request);

        return $response;
    }

    protected function applyCors(Response $response, Request $request): void
    {
        $origin = (string) $request->headers->get('Origin', '');
        $origins = (array) config('toolkit.middleware.cors.origins', ['*']);
        $credentials = (bool) config('toolkit.middleware.cors.credentials', false);

        $allowed = in_array('*', $origins, true) || in_array($origin, $origins, true);
        if (! $allowed) {
            return;
        }

        $allowOrigin = in_array('*', $origins, true) && ! $credentials ? '*' : $origin;

        $response->headers->set('Access-Control-Allow-Origin', $allowOrigin);
        $response->headers->set('Vary', 'Origin', false);

        if ($credentials) {
            $response->headers->set('Access-Control-Allow-Credentials', 'true');
        }

        if ($request->getMethod() === 'OPTIONS') {
            $response->headers->set('Access-Control-Allow-Methods', implode(', ', (array) config('toolkit.middleware.cors.methods', [])));
            $response->headers->set('Access-Control-Allow-Headers', implode(', ', (array) config('toolkit.middleware.cors.headers', [])));
            $response->headers->set('Access-Control-Max-Age', (string) config('toolkit.middleware.cors.max_age', 86400));
        }
    }

    protected function corsEnabled(): bool
    {
        return (bool) config('toolkit.middleware.cors.enabled', true);
    }

    protected function requestIdEnabled(): bool
    {
        return (bool) config('toolkit.middleware.request_id.enabled', true);
    }

    protected function forceJson(): bool
    {
        return (bool) config('toolkit.middleware.exception.force_json', true);
    }
}