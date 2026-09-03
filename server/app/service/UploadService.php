<?php

declare(strict_types=1);

namespace app\service;

use app\exception\BusinessException;
use Webman\Http\UploadFile;

class UploadService
{
    private const array ALLOWED = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
    private const int MAX_SIZE = 5 * 1024 * 1024;

    /**
     * 校验并保存上传的图片，返回可访问 URL 与相对路径
     *
     * @return array{url: string, path: string}
     */
    public static function saveImage(?UploadFile $file): array
    {
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

        $path = '/uploads/' . date('Ym') . '/' . $filename;
        $url  = rtrim((string)env('APP_URL', ''), '/') . $path;

        return ['url' => $url, 'path' => $path];
    }
}
