<?php

namespace ESolution\BNIPayment\Clients;

class BniQrisClient extends BaseClient
{
    /**
     * QRIS channel identifier for logging & tracing.
     *
     * @var string
     */
    protected string $channel = 'qris';

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
    public function generateQr(array $payload, $clientId = '', $prefix = '', $secret = ''): array
    {
        // Inject merchantId & terminalId default sesuai config bila belum diisi
        if (empty($payload['merchantId'])) {
            $payload['merchantId'] = config('bni.qris.merchant_id');
        }

        if (empty($payload['terminalId'])) {
            $payload['terminalId'] = config('bni.qris.terminal_id');
        }

        // Path mengikuti konfigurasi; disesuaikan dengan Path di PDF (SNAP)
        // Misalnya: '/v1.0/debit/payment-qr/qr-mpm'
        $path = config('bni.qris.path_generate_qr')
            ?? config('bni.qris.path_create_dynamic', '/qris/create');

        return $this->request('POST', $path, $payload, $clientId, $prefix, $secret);
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
    public function queryPayment($payloadOrReferenceNo, $clientId = '', $prefix = '', $secret = ''): array
    {
        if (is_string($payloadOrReferenceNo)) {
            $payload = ['partnerReferenceNo' => $payloadOrReferenceNo];
        } else {
            $payload = $payloadOrReferenceNo;
        }

        // Path mengikuti konfigurasi; sesuaikan dengan Path MPM Query Payment di PDF.
        // Misalnya: '/v1.0/debit/payment-qr/qr-mpm/status'
        $path = config('bni.qris.path_query_payment')
            ?? config('bni.qris.path_inquiry_status', '/qris/inquiry');

        return $this->request('POST', $path, $payload, $clientId, $prefix, $secret);
    }

    /**
     * Backward-compatible alias untuk inquiry status lama.
     *
     * Versi lama hanya mengirim "order_id".
     * Untuk mengikuti SNAP, disarankan:
     * - Ganti pemanggilan ke queryPayment(['partnerReferenceNo' => $partnerRef])
     *   atau panggil queryPayment($partnerRef).
     *
     * Di sini, demi kompatibilitas:
     * - "orderId" masih diterima sebagai string
     * - dikirim sebagai 'partnerReferenceNo' di payload ke endpoint baru.
     *
     * @deprecated Gunakan queryPayment() agar mengikuti istilah SNAP.
     *
     * @param  string      $orderId
     * @param  string|null $clientId
     * @param  string|null $prefix
     * @param  string|null $secret
     * @return array
     */
    public function inquiryStatus(string $orderId, $clientId = '', $prefix = '', $secret = ''): array
    {
        // Mapping orderId lama -> partnerReferenceNo baru
        $payload = ['partnerReferenceNo' => $orderId];

        return $this->queryPayment($payload, $clientId, $prefix, $secret);
    }
}
