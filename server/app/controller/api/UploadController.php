<?php

declare(strict_types=1);

namespace app\controller\api;

use app\service\UploadService;
use app\support\Result;
use support\Request;
use support\Response;

class UploadController
{
    public function image(Request $request): Response
    {
        return Result::success(UploadService::saveImage($request->file('file')));
    }
}
