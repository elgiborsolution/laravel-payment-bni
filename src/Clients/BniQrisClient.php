<?php

namespace ESolution\BNIPayment\Clients;

use ESolution\BNIPayment\Services\BniQrisAuth;

use ESolution\BNIPayment\Models\BniBilling;

class BniQrisClient extends BaseClient
{
    /**
     * QRIS channel identifier for logging & tracing.
     *
     * @var string
     */
    protected string $channel = 'qris';

    protected array $config;
    public function __construct(array $config = [])
    {
        $this->config = $config ?: (config('bri.qris') ?? []);

    }


    /**
     * Generate Dynamic QR (MPM) sesuai spesifikasi BNI QRIS MPM (SNAP BI).
     *
     * Struktur payload mengikuti sample request di dokumen:
     *
     * {
     *   "partnerReferenceNo": "2020102900000000000001",
     *   "amount": {
     *      "value": "12345678.00",
     *      "currency": "IDR"
     *   },
     *   "merchantId": "00007100010926",
     *   "terminalId": "213141251124",
     *   "validityPeriod": "2009-07-03T12:08:56-07:00",
     *   "additionalInfo": {
     *      "tipIndicator": "01|02|03",
     *      "tipValueFixed": "1000.00",
     *      "tipValuePercentage": "5.00",
     *      "additionalData": "Optional information for customer"
     *   }
     * }
     *
     * Catatan:
     * - merchantId & terminalId otomatis diisi dari config('bni.qris.merchant_id' / 'terminal_id')
     *   jika belum ada di payload.
     * - Path endpoint diambil dari:
     *   - config('bni.qris.path_generate_qr')
     *   - fallback ke config('bni.qris.path_create_dynamic', '/qris/create')
     *
     * @param  array       $payload   Payload QRIS sesuai spesifikasi BNI QRIS MPM.
     * @param  string|null $clientId  Client ID (kalau diperlukan untuk logging / enkripsi).
     * @param  string|null $prefix    Prefix (kalau diperlukan oleh BaseClient).
     * @param  string|null $secret    Secret untuk enkripsi/signature (tergantung implementasi BaseClient).
     * @return array                  Response dari API QRIS.
     */
    public function generateQr(array $payload): array
    {
        // Inject merchantId & terminalId default sesuai config bila belum diisi
        if (empty($payload['merchantId'])) {
            $payload['merchantId'] = $this->config['merchant_id'];
        }

        // Path mengikuti konfigurasi; disesuaikan dengan Path di PDF (SNAP)
        // Misalnya: '/v1.0/debit/payment-qr/qr-mpm'
        $endPoint = '/qr/qr-mpm-generate';//config('bni.qris.path_generate_qr')?? config('bni.qris.path_create_dynamic', '/qris/create');

        $version = trim($this->config['version'] ?? 'v1.0', '/');

        $path = '/' . $version . $endPoint;

        $res = $this->qrisRequest($this->config, 'POST', $path, $payload);

        $data = $res ?? [];
        $trxId = $data['partnerReferenceNo'] ?? null;
        if (!empty($trxId)) {
            BniBilling::updateOrCreate(['trx_id' => $trxId], [
                'qris_reference_no' => $data['referenceNo'] ?? null,
                'qris_content' => $data['qrContent'] ?? null,
                'qris_bill_number' => $data['additionalInfo']['billNumber'] ?? null,
                'description' => $payload['additionalInfo']['additionalData'] ?? null,
                'trx_amount' => $payload['amount']['value'] ?? null,
                'customer_name' => $payload['customer_name'] ?? null,
                'customer_email' => $payload['customer_email'] ?? null,
                'customer_phone' => $payload['customer_phone'] ?? null,
                'billing_type' => 'qris',
                'expired_at' => isset($payload['validityPeriod']) ? date('Y-m-d H:i:s', strtotime($payload['validityPeriod'])) : null,
            ]);
        }
        return $res;
    }

    /**
     * MPM Query Payment / Inquiry Status.
     *
     * Di spesifikasi SNAP, query biasanya menggunakan data seperti:
     * - partnerReferenceNo (atau originalPartnerReferenceNo)
     * - referenceNo (jika diperlukan)
     *
     * Untuk fleksibel dan tetap aman terhadap perubahan dokumen,
     * method ini menerima payload array mentah, dan:
     * - Kalau hanya diberi $partnerReferenceNo (string), maka akan dibuatkan payload:
     *   ['partnerReferenceNo' => $partnerReferenceNo]
     * - Kalau ingin mengikuti dokumen terbaru persis, bisa langsung kirim payload lengkap.
     *
     * Contoh payload minimal:
     *  [
     *      "partnerReferenceNo" => "2020102900000000000001",
     *  ]
     *
     * @param  array|string $payloadOrReferenceNo  Payload inquiry lengkap
     *                                            atau hanya partnerReferenceNo.
     * @param  string|null  $clientId
     * @param  string|null  $prefix
     * @param  string|null  $secret
     * @return array
     */
    public function queryPayment($payloadOrReferenceNo, $serviceCode = 51): array
    {

        if (is_string($payloadOrReferenceNo)) {
            $payload = ['partnerReferenceNo' => $payloadOrReferenceNo];
        } else {
            $payload = $payloadOrReferenceNo;
        }

        // Path mengikuti konfigurasi; sesuaikan dengan Path MPM Query Payment di PDF.
        // Misalnya: '/v1.0/debit/payment-qr/qr-mpm/status'
        // $path = config('bni.qris.path_query_payment')?? config('bni.qris.path_inquiry_status', '/qris/inquiry');
        $endPoint = '/qr/qr-mpm-generate';

        $version = trim($this->config['version'] ?? 'v1.0', '/');

        $path = '/' . $version . $endPoint;


        $res = $this->qrisRequest($this->config, 'POST', $path, $payload, $clientId, $prefix, $secret);

        $data = $res ?? [];
        $trxId = $data['originalPartnerReferenceNo'] ?? null;
        if ($trxId) {
            BniBilling::where('trx_id', $trxId)->where('qris_reference_no', ($data['originalReferenceNo']??null))->update([
                'paid_at' => isset($data['paidTime']) ? date('Y-m-d H:i:s', strtotime($data['paidTime'])) : null,
                'qris_status' => $data['latestTransactionStatus'] ?? null,
            ]);
        }
    }

}
