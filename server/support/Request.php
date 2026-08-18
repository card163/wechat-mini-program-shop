<?php
/**
 * This file is part of webman.
 *
 * Licensed under The MIT License
 * For full copyright and license information, please see the MIT-LICENSE.txt
 * Redistributions of files must retain the above copyright notice.
 *
 * @author    walkor<walkor@workerman.net>
 * @copyright walkor<walkor@workerman.net>
 * @link      http://www.workerman.net/
 * @license   http://www.opensource.org/licenses/mit-license.php MIT License
 */

namespace support;

/**
 * Class Request
 * @package support
 */
class Request extends \Webman\Http\Request
{
    /** 当前登录会员ID，由 MemberAuth 中间件写入 */
    public ?int $memberId = null;

    /** 当前登录管理员ID，由 AdminAuth 中间件写入 */
    public ?int $adminId = null;

    /** 当前登录管理员角色，1超级管理员 2店员 */
    public ?int $adminRole = null;
}