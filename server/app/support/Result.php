<?php

declare(strict_types=1);

namespace app\support;

use stdClass;
use support\Response;

class Result
{
    public const int SUCCESS      = 0;
    public const int FAIL         = 1;
    public const int UNAUTHORIZED = 401;
    public const int FORBIDDEN    = 403;
    public const int NOT_FOUND    = 404;
    public const int SERVER_ERROR = 500;

    public static function success(mixed $data = null, string $msg = 'ok'): Response
    {
        return json([
            'code' => self::SUCCESS,
            'msg'  => $msg,
            'data' => $data ?? new stdClass(),
        ]);
    }

    public static function error(string $msg, int $code = self::FAIL, mixed $data = null): Response
    {
        return json([
            'code' => $code,
            'msg'  => $msg,
            'data' => $data ?? new stdClass(),
        ]);
    }

    public static function page(iterable $list, int $total, int $page, int $pageSize): Response
    {
        return self::success([
            'list'      => $list,
            'total'     => $total,
            'page'      => $page,
            'page_size' => $pageSize,
        ]);
    }
}
