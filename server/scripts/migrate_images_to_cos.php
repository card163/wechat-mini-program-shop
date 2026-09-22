<?php

declare(strict_types=1);

/**
 * 一次性迁移工具：把 public/uploads 下的历史图片上传到腾讯云 COS，
 * 并把数据库里引用旧本地 URL 的字段批量替换成新的 COS URL。
 *
 * 用法（在部署了 server/ 代码、且 .env 已配置 COS_* 的机器上执行）：
 *   php scripts/migrate_images_to_cos.php
 *
 * 幂等：
 *   - 文件按相同 key 上传到 COS 会覆盖同名对象，重复上传无副作用；
 *   - 数据库替换用 WHERE col LIKE '旧前缀%'，已替换过的行不会再次匹配。
 */

use app\service\CosService;
use Illuminate\Database\Capsule\Manager as DB;

chdir(dirname(__DIR__));
require dirname(__DIR__) . '/vendor/autoload.php';
require dirname(__DIR__) . '/support/bootstrap.php';

$uploadsDir = public_path() . '/uploads';
if (!is_dir($uploadsDir)) {
    echo "目录不存在，无需迁移文件：{$uploadsDir}\n";
    exit(0);
}

// ------------------------- 第一步：上传本地历史图片到 COS -------------------------
$uploaded = 0;
$failed   = 0;
$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($uploadsDir, FilesystemIterator::SKIP_DOTS)
);
foreach ($iterator as $file) {
    /** @var SplFileInfo $file */
    if (!$file->isFile()) {
        continue;
    }
    $relative  = ltrim(str_replace($uploadsDir, '', $file->getPathname()), '/');
    $key       = 'uploads/' . str_replace('\\', '/', $relative);
    $extension = strtolower($file->getExtension());
    try {
        $url = CosService::uploadFile($file->getPathname(), $key, $extension);
        $uploaded++;
        echo "已上传：{$key} -> {$url}\n";
    } catch (Throwable $e) {
        $failed++;
        fwrite(STDERR, "上传失败：{$key} - {$e->getMessage()}\n");
    }
}
echo "文件上传完成：成功 {$uploaded}，失败 {$failed}\n";

if ($failed > 0) {
    fwrite(STDERR, "存在上传失败的文件，为避免数据库指向不存在的 COS 对象，本次跳过数据库URL替换，请排查后重新执行本脚本。\n");
    exit(1);
}

// ------------------------- 第二步：替换数据库里的旧URL前缀 -------------------------
$oldPrefix = rtrim((string)env('APP_URL', ''), '/') . '/uploads/';
$newPrefix = CosService::config()['domain'] . '/uploads/';

if ($oldPrefix === '/uploads/') {
    fwrite(STDERR, "APP_URL 未配置，无法确定旧URL前缀，已中止\n");
    exit(1);
}

echo "替换前缀：{$oldPrefix} -> {$newPrefix}\n";

/** @var array<int, array{table:string, column:string, json:bool}> $targets */
$targets = [
    ['table' => 'nf_member',         'column' => 'avatar',      'json' => false],
    ['table' => 'nf_admin_user',     'column' => 'avatar',      'json' => false],
    ['table' => 'nf_banner',         'column' => 'image',       'json' => false],
    ['table' => 'nf_goods',          'column' => 'cover',       'json' => false],
    ['table' => 'nf_goods',          'column' => 'images',      'json' => true],
    ['table' => 'nf_exchange_goods', 'column' => 'cover',       'json' => false],
    ['table' => 'nf_wine_storage',   'column' => 'images',      'json' => true],
    ['table' => 'nf_order_item',     'column' => 'goods_cover', 'json' => false],
];

foreach ($targets as $t) {
    $table  = $t['table'];
    $column = $t['column'];
    if ($t['json']) {
        $affected = DB::connection()->update(
            "UPDATE `{$table}` SET `{$column}` = CAST(REPLACE(CAST(`{$column}` AS CHAR), ?, ?) AS JSON) "
            . "WHERE `{$column}` IS NOT NULL AND CAST(`{$column}` AS CHAR) LIKE ?",
            [$oldPrefix, $newPrefix, '%' . $oldPrefix . '%']
        );
    } else {
        $affected = DB::connection()->update(
            "UPDATE `{$table}` SET `{$column}` = REPLACE(`{$column}`, ?, ?) WHERE `{$column}` LIKE ?",
            [$oldPrefix, $newPrefix, $oldPrefix . '%']
        );
    }
    echo "{$table}.{$column}：更新 {$affected} 行\n";
}

echo "迁移完成\n";
