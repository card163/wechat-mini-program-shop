<?php

declare(strict_types=1);

namespace app\controller\admin;

use app\exception\BusinessException;
use app\model\Setting;
use app\service\SettingService;
use app\support\Result;
use support\Request;
use support\Response;

class SettingController
{
    private const array GROUPS = ['base', 'point', 'order', 'wine'];

    public function show(Request $request, string $group): Response
    {
        $this->assertGroup($group);

        return Result::success(SettingService::group($group));
    }

    public function save(Request $request, string $group): Response
    {
        $this->assertGroup($group);

        $data = $request->post();
        if (!is_array($data) || $data === []) {
            throw new BusinessException('没有需要保存的内容');
        }

        // 只允许更新已存在的配置项，避免写入任意键值
        $allowed = array_keys(SettingService::group($group));
        foreach ($data as $key => $value) {
            if (!in_array($key, $allowed, true)) {
                continue;
            }
            Setting::query()
                ->where('group', $group)
                ->where('key', $key)
                ->update(['value' => is_scalar($value) ? (string)$value : json_encode($value, JSON_UNESCAPED_UNICODE)]);
        }

        return Result::success(SettingService::group($group), '保存成功');
    }

    private function assertGroup(string $group): void
    {
        if (!in_array($group, self::GROUPS, true)) {
            throw new BusinessException('配置分组不存在', Result::NOT_FOUND);
        }
    }
}
