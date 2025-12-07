<?php

namespace ESolution\BNIPayment\Services;

use Carbon\CarbonImmutable;
use ESolution\BNIPayment\Models\BniBilling;
use ESolution\BNIPayment\Clients\BniVaClient;
use ESolution\BNIPayment\Exceptions\BniApiException;
use ESolution\BNIPayment\Events\BniBillingPaid;
use ESolution\BNIPayment\Events\BniBillingExpired;

class Reconciler
{
    public function __construct(protected BniVaClient $va) {}

    public function reconcile(int $limit = 100): array
    {
        $now = CarbonImmutable::now();
        $targets = BniBilling::query()
            ->whereNull('paid_at')
            ->where(function ($q) use ($now) {
                $q->whereNull('expired_at')->orWhere('expired_at', '>', $now->subDay());
            })
            ->orderBy('id')
            ->limit($limit)
            ->get();

        $results = ['processed' => 0, 'paid' => 0, 'errors' => 0];

        foreach ($targets as $bill) {
            $results['processed']++;
            try {
                $res = $this->va->inquiryBilling($bill->trx_id);
                $data = $res['data'] ?? [];

                $wasPaid = ! (is_null($bill->getOriginal('paid_at')));
                $wasExpired = ! (is_null($bill->getOriginal('expired_at')));

                $bill->virtual_account = $data['virtual_account'] ?? $bill->virtual_account;
                $bill->trx_amount = $data['trx_amount'] ?? $bill->trx_amount;
                $bill->customer_name = $data['customer_name'] ?? $bill->customer_name;
                $bill->customer_email = $data['customer_email'] ?? $bill->customer_email;
                $bill->customer_phone = $data['customer_phone'] ?? $bill->customer_phone;
                $bill->billing_type = $data['billing_type'] ?? $bill->billing_type;
                $bill->description = $data['description'] ?? $bill->description;
                $bill->va_status = $data['va_status'] ?? $bill->va_status;
                $bill->payment_amount = $data['payment_amount'] ?? $bill->payment_amount;
                $bill->payment_ntb = $data['payment_ntb'] ?? $bill->payment_ntb;
                $bill->last_inquiry_at = now();

                $paidAtIso = $data['datetime_payment_iso8601'] ?? null;
                $paidAt = $data['datetime_payment'] ?? null;
                if (!empty($paidAtIso)) {
                    $bill->paid_at = date('Y-m-d H:i:s', strtotime($paidAtIso));
                } elseif (!empty($paidAt)) {
                    $bill->paid_at = date('Y-m-d H:i:s', strtotime($paidAt));
                }

                if ($bill->paid_at) $results['paid']++;

                $expiredIso = $data['datetime_expired_iso8601'] ?? null;
                if (!empty($expiredIso)) $bill->expired_at = date('Y-m-d H:i:s', strtotime($expiredIso));

                $bill->save();

                if (!$wasPaid && $bill->paid_at) {
                    event(new BniBillingPaid($bill));
                }
                if (!$wasExpired && $bill->expired_at && !$bill->paid_at) {
                    event(new BniBillingExpired($bill));
                }
            } catch (BniApiException $e) {
                $results['errors']++;
            }
        }

        return $results;
    }
}
