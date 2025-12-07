<?php

namespace ESolution\BNIPayment\Clients;

class BniQrisClient extends BaseClient
{
    protected string $channel = 'qris';

    public function createDynamic(array $payload): array
    {
        $path = config('bni.qris.path_create_dynamic', '/qris/create');
        return $this->request('POST', $path, $payload);
    }

    public function inquiryStatus(string $orderId): array
    {
        $path = config('bni.qris.path_inquiry_status', '/qris/inquiry');
        return $this->request('POST', $path, ['order_id' => $orderId]);
    }
}
