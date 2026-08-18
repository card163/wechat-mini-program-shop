<?php

declare(strict_types=1);

namespace app\exception;

use app\support\Result;
use Respect\Validation\Exceptions\ValidationException;
use Throwable;
use Webman\Exception\ExceptionHandler;
use Webman\Http\Request;
use Webman\Http\Response;

class Handler extends ExceptionHandler
{
    public $dontReport = [
        BusinessException::class,
        ValidationException::class,
    ];

    public function render(Request $request, Throwable $exception): Response
    {
        if ($exception instanceof BusinessException) {
            return Result::error($exception->getMessage(), (int)$exception->getCode() ?: Result::FAIL);
        }

        if ($exception instanceof ValidationException) {
            return Result::error($exception->getMessage(), Result::FAIL);
        }

        if (!$this->debug) {
            return Result::error('服务器繁忙，请稍后再试', Result::SERVER_ERROR);
        }

        return Result::error($exception->getMessage(), Result::SERVER_ERROR, [
            'exception' => $exception::class,
            'file'      => $exception->getFile() . ':' . $exception->getLine(),
            'trace'     => explode("\n", $exception->getTraceAsString()),
        ]);
    }
}
