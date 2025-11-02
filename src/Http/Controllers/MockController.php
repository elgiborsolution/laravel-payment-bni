<?php

namespace ESolution\BNIPayment\Http\Controllers;

use Illuminate\Routing\Controller;
use Illuminate\Http\Request;

class MockController extends Controller
{
    public function createVa(Request $request)
    {
        return response()->json([
            'status' => '000',
            'data' => [
                'virtual_account' => '8001000000000001',
                'trx_id' => $request->input('trx_id', 'INV-MOCK-1')
            ]
        ]);
    }

    public function updateVa(Request $request)
    {
        return response()->json([
            'status' => '000',
            'data' => [
                'virtual_account' => $request->input('virtual_account', '8320000000000001'),
                'trx_id' => $request->input('trx_id', 'INV-MOCK-1')
            ]
        ]);
    }

    public function inquiryVa(Request $request)
    {
        return response()->json([
            'status' => '000',
            'data' => [
                'client_id' => $request->input('client_id', '320'),
                'trx_id' => $request->input('trx_id', 'INV-MOCK-1'),
                'trx_amount' => '100000',
                'virtual_account' => '8001000000000001',
                'customer_name' => 'Mr. X',
                'customer_phone' => '08123123123',
                'customer_email' => 'xxx@email.com',
                'datetime_created' => '2016-02-01 16:00:00',
                'datetime_expired' => '2016-03-01 16:00:00',
                'datetime_last_updated' => '2016-02-10 16:00:00',
                'datetime_payment' => '2016-02-23 23:23:09',
                'payment_ntb' => '023589',
                'payment_amount' => '100000',
                'va_status' => '2',
                'billing_type' => 'c',
                'description' => 'Payment of Trx 123000001',
                'datetime_created_iso8601' => '2016-02-01T16:00:00+07:00',
                'datetime_expired_iso8601' => '2016-03-01T16:00:00+07:00',
                'datetime_last_updated_iso8601' => '2016-02-10T16:00:00+07:00',
                'datetime_payment_iso8601' => '2015-06-23T23:23:09+07:00'
            ]
        ]);
    }
}
