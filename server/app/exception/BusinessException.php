<?php

declare(strict_types=1);

namespace app\exception;

use app\support\Result;
use Throwable;
use Webman\Exception\BusinessException as WebmanBusinessException;

/**
 * 业务异常，被 Handler 捕获后转成统一响应体，不写错误日志
 */
class BusinessException extends WebmanBusinessException
{
    public function __construct(string $message = '操作失败', int $code = Result::FAIL, ?Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
