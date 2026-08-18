<?php

declare(strict_types=1);

namespace app\controller\admin;

use app\exception\BusinessException;
use app\support\Result;
use support\Request;
use support\Response;

class UploadController
{
    private const array ALLOWED = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
    private const int MAX_SIZE = 5 * 1024 * 1024;

    public function image(Request $request): Response
    {
        $file = $request->file('file');
        if ($file === null || !$file->isValid()) {
            throw new BusinessException('请选择要上传的图片');
        }
        if ($file->getSize() > self::MAX_SIZE) {
            throw new BusinessException('图片不能超过 5MB');
        }

        $extension = strtolower($file->getUploadExtension());
        if (!in_array($extension, self::ALLOWED, true)) {
            throw new BusinessException('仅支持 jpg / png / gif / webp 格式');
        }

        // 以真实图片内容为准，避免伪造扩展名上传可执行文件
        $info = @getimagesize($file->getRealPath());
        if ($info === false) {
            throw new BusinessException('文件不是有效的图片');
        }

        $dir = public_path() . '/uploads/' . date('Ym');
        if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
            throw new BusinessException('上传目录创建失败');
        }

        $filename = bin2hex(random_bytes(16)) . '.' . $extension;
        $file->move($dir . '/' . $filename);

        $url = rtrim((string)env('APP_URL', ''), '/') . '/uploads/' . date('Ym') . '/' . $filename;

        return Result::success(['url' => $url, 'path' => '/uploads/' . date('Ym') . '/' . $filename]);
    }
}
