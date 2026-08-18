<?php

declare(strict_types=1);

namespace app\service;

use app\exception\BusinessException;
use app\model\Member;
use app\support\Result;
use app\support\Token;

class MemberAuthService
{
    /**
     * @return array{token: string, expires_at: int, is_new: bool, member: array<string, mixed>}
     */
    public static function login(string $code): array
    {
        $session = WechatService::code2Session($code);

        $member = Member::query()->where('openid', $session['openid'])->first();
        $isNew  = $member === null;

        if ($isNew) {
            $member = new Member();
            $member->openid   = $session['openid'];
            $member->unionid  = $session['unionid'];
            $member->nickname = '';
            $member->avatar   = '';
            $member->status   = Member::STATUS_NORMAL;
        } elseif ($session['unionid'] !== '' && (string)$member->unionid === '') {
            $member->unionid = $session['unionid'];
        }

        if ((int)$member->status !== Member::STATUS_NORMAL) {
            throw new BusinessException('账号已被禁用', Result::FORBIDDEN);
        }

        $member->last_login_at = date('Y-m-d H:i:s');
        $member->save();

        $token = Token::issue(Token::GUARD_MEMBER, (int)$member->id);

        return [
            'token'      => $token['token'],
            'expires_at' => $token['expires_at'],
            'is_new'     => $isNew,
            'member'     => self::profile($member),
        ];
    }

    public static function updateProfile(int $memberId, ?string $nickname, ?string $avatar): Member
    {
        $member = Member::query()->findOrFail($memberId);

        if ($nickname !== null && $nickname !== '') {
            $member->nickname = mb_substr($nickname, 0, 32);
        }
        if ($avatar !== null && $avatar !== '') {
            $member->avatar = $avatar;
        }
        $member->save();

        return $member;
    }

    public static function bindPhone(int $memberId, string $code): string
    {
        $phone  = WechatService::getPhoneNumber($code);
        $member = Member::query()->findOrFail($memberId);

        $member->phone = $phone;
        $member->save();

        return $phone;
    }

    /**
     * 会员公开信息，禁止返回 openid 等敏感字段
     *
     * @return array<string, mixed>
     */
    public static function profile(Member $member): array
    {
        return [
            'id'           => (int)$member->id,
            'nickname'     => self::displayName((string)$member->nickname),
            'avatar'       => (string)$member->avatar,
            'phone'        => self::maskPhone((string)$member->phone),
            'balance'      => (int)$member->balance,
            'gift_balance' => (int)$member->gift_balance,
            'point'        => (int)$member->point,
            'total_point'  => (int)$member->total_point,
        ];
    }

    public static function displayName(string $nickname): string
    {
        return $nickname === '' ? '牌友' : $nickname;
    }

    public static function maskPhone(string $phone): string
    {
        return strlen($phone) === 11 ? substr($phone, 0, 3) . '****' . substr($phone, -4) : $phone;
    }
}
