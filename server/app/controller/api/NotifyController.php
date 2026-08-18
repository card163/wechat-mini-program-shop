<?php

declare(strict_types=1);

namespace app\controller\api;

use app\model\PayNotifyLog;
use app\service\OrderService;
use app\service\RechargeService;
use app\service\WechatPayService;
use app\support\Sn;
use support\Log;
use support\Request;
use support\Response;
use Throwable;

class NotifyController
{
    /**
     * 微信支付回调：先落日志再处理业务，按订单号幂等
     */
    public function wechatPay(Request $request): Response
    {
        $body    = $request->rawBody();
        $headers = array_change_key_case($request->header(), CASE_LOWER);

        try {
            $resource = WechatPayService::decodeNotify($headers, $body);
        } catch (Throwable $e) {
            Log::error('微信支付回调验签失败：' . $e->getMessage());
            return json(['code' => 'FAIL', 'message' => '验签失败'])->withStatus(401);
        }

        $orderNo       = (string)($resource['out_trade_no'] ?? '');
        $transactionId = (string)($resource['transaction_id'] ?? '');
        $tradeState    = (string)($resource['trade_state'] ?? '');
        $amount        = (int)($resource['amount']['total'] ?? 0);
        $isRecharge    = str_starts_with($orderNo, Sn::RECHARGE);

        $log = new PayNotifyLog();
        $log->biz_type       = $isRecharge ? PayNotifyLog::BIZ_RECHARGE : PayNotifyLog::BIZ_ORDER;
        $log->order_no       = $orderNo;
        $log->transaction_id = $transactionId;
        $log->amount         = $amount;
        $log->trade_state    = $tradeState;
        $log->payload        = $resource;
        $log->handled        = 0;
        $log->save();

        if ($tradeState !== 'SUCCESS') {
            return json(['code' => 'SUCCESS', 'message' => '已忽略非成功状态']);
        }

        try {
            if ($isRecharge) {
                RechargeService::markPaid($orderNo, $transactionId, $amount);
            } else {
                OrderService::markPaidByWechat($orderNo, $transactionId, $amount);
            }

            $log->handled = 1;
            $log->save();
        } catch (Throwable $e) {
            Log::error("微信支付回调处理失败[$orderNo]：" . $e->getMessage());
            return json(['code' => 'FAIL', 'message' => '处理失败'])->withStatus(500);
        }

        return json(['code' => 'SUCCESS', 'message' => '成功']);
    }
}
