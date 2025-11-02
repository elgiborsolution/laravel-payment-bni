<?php

namespace ESolution\BNIPayment\Clients;

use ESolution\BNIPayment\Models\BniBilling;

final class VaClient extends BaseClient
{
    protected string $channel = 'va';

    public function createBilling(array $payload): array
    {
        $payload['client_id'] = $payload['client_id'] ?? bni_config('client_id');
        $res = $this->request('POST', '/customer/ecollection/create', $payload);
        $data = $res['data'] ?? [];
        $trxId = $data['trx_id'] ?? $payload['trx_id'] ?? null;
        if ($trxId) {
            BniBilling::updateOrCreate(['trx_id' => $trxId], [
                'virtual_account' => $data['virtual_account'] ?? ($payload['virtual_account'] ?? null),
                'trx_amount' => $payload['trx_amount'] ?? null,
                'customer_name' => $payload['customer_name'] ?? null,
                'customer_email' => $payload['customer_email'] ?? null,
                'customer_phone' => $payload['customer_phone'] ?? null,
                'billing_type' => $payload['billing_type'] ?? null,
                'description' => $payload['description'] ?? null,
                'expired_at' => isset($payload['datetime_expired']) ? date('Y-m-d H:i:s', strtotime($payload['datetime_expired'])) : null,
            ]);
        }
        return $res;
    }

    public function updateBilling(array $payload): array
    {
        $payload['client_id'] = $payload['client_id'] ?? bni_config('client_id');
        $payload['type'] = $payload['type'] ?? 'updatebilling';
        $res = $this->request('PUT', '/customer/ecollection/update', $payload);
        $data = $res['data'] ?? [];
        $trxId = $data['trx_id'] ?? $payload['trx_id'] ?? null;
        if ($trxId) {
            BniBilling::updateOrCreate(['trx_id' => $trxId], [
                'virtual_account' => $data['virtual_account'] ?? ($payload['virtual_account'] ?? null),
                'trx_amount' => $payload['trx_amount'] ?? null,
                'customer_name' => $payload['customer_name'] ?? null,
                'customer_email' => $payload['customer_email'] ?? null,
                'customer_phone' => $payload['customer_phone'] ?? null,
                'billing_type' => $payload['billing_type'] ?? null,
                'description' => $payload['description'] ?? null,
            ]);
        }
        return $res;
    }

    public function inquiryBilling(string $trxId, ?string $clientId = null): array
    {
        $payload = [
            'type' => 'inquirybilling',
            'client_id' => $clientId ?? bni_config('client_id'),
            'trx_id' => $trxId,
        ];
        return $this->request('POST', '/customer/ecollection/inquiry', $payload);
    }
}
