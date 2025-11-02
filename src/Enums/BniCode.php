<?php

namespace ESolution\BNIPayment\Enums;

enum BniCode: string
{
    case SUCCESS = '000';
    case INCOMPLETE_INVALID_PARAMS = '001';
    case IP_OR_CLIENTID_INVALID = '002';
    case SERVICE_NOT_FOUND = '004';
    case SERVICE_NOT_DEFINED = '005';
    case INVALID_VA_NUMBER = '006';
    case INVALID_BILLING_NUMBER = '007';
    case TECHNICAL_FAILURE = '008';
    case UNEXPECTED_ERROR = '009';
    case REQUEST_TIMEOUT = '010';
    case BILLINGTYPE_NOT_MATCH_AMOUNT = '011';
    case INVALID_EXPIRY = '012';
    case IDR_DECIMAL_NOT_ALLOWED = '013';
    case VA_SHOULD_NOT_DEFINED_WHEN_BILLING_SET = '014';
    case INVALID_PERMISSION = '015';
    case INVALID_BILLING_TYPE = '016';
    case CUSTOMER_NAME_CANNOT_BE_USED = '017';
    case BILLING_PAID = '100';
    case BILLING_NOT_FOUND = '101';
    case VA_IN_USE = '102';
    case BILLING_EXPIRED = '103';
    case BILLING_NUMBER_IN_USE = '104';
    case DUPLICATE_BILLING_ID = '105';
    case AMOUNT_CANNOT_CHANGED = '107';
    case DATA_NOT_FOUND = '108';
    case EXCEED_DAILY_LIMIT = '110';
    case FAILED_SEND_SMS = '200';
    case SMS_ONLY_FIXED_PAYMENT = '201';
    case BILLINGTYPE_NOT_SUPPORTED_FOR_CLIENT = '801';
    case TOO_MANY_INQUIRY = '996';
    case SYSTEM_TEMP_OFFLINE = '997';
    case INVALID_CONTENT_TYPE = '998';
    case INTERNAL_ERROR = '999';

    public static function describe(string $code): string
    {
        return match($code) {
            self::SUCCESS->value => 'Success',
            self::INCOMPLETE_INVALID_PARAMS->value => 'Incomplete/invalid Parameter(s).',
            self::IP_OR_CLIENTID_INVALID->value => 'IP address not allowed or wrong Client ID.',
            self::SERVICE_NOT_FOUND->value => 'Service not found.',
            self::SERVICE_NOT_DEFINED->value => 'Service not defined.',
            self::INVALID_VA_NUMBER->value => 'Invalid VA Number.',
            self::INVALID_BILLING_NUMBER->value => 'Invalid Billing Number.',
            self::TECHNICAL_FAILURE->value => 'Technical Failure.',
            self::UNEXPECTED_ERROR->value => 'Unexpected Error.',
            self::REQUEST_TIMEOUT->value => 'Request Timeout.',
            self::BILLINGTYPE_NOT_MATCH_AMOUNT->value => 'Billing type does not match billing amount.',
            self::INVALID_EXPIRY->value => 'Invalid expiry date/time.',
            self::IDR_DECIMAL_NOT_ALLOWED->value => 'IDR currency cannot have billing amount with decimal fraction.',
            self::VA_SHOULD_NOT_DEFINED_WHEN_BILLING_SET->value => 'VA Number should not be defined when Billing Number is set.',
            self::INVALID_PERMISSION->value => 'Invalid Permission(s)',
            self::INVALID_BILLING_TYPE->value => 'Invalid Billing Type',
            self::CUSTOMER_NAME_CANNOT_BE_USED->value => 'Customer Name cannot be used.',
            self::BILLING_PAID->value => 'Billing has been paid',
            self::BILLING_NOT_FOUND->value => 'Billing not found.',
            self::VA_IN_USE->value => 'VA Number is in use.',
            self::BILLING_EXPIRED->value => 'Billing has been expired.',
            self::BILLING_NUMBER_IN_USE->value => 'Billing Number is in use.',
            self::DUPLICATE_BILLING_ID->value => 'Duplicate Billing ID.',
            self::AMOUNT_CANNOT_CHANGED->value => 'Amount can not be changed.',
            self::DATA_NOT_FOUND->value => 'Data not found.',
            self::EXCEED_DAILY_LIMIT->value => 'Exceed Daily Limit Transaction',
            self::FAILED_SEND_SMS->value => 'Failed to send SMS Payment.',
            self::SMS_ONLY_FIXED_PAYMENT->value => 'SMS Payment can only be used with Fixed Payment.',
            self::BILLINGTYPE_NOT_SUPPORTED_FOR_CLIENT->value => 'Billing type not supported for this Client ID.',
            self::TOO_MANY_INQUIRY->value => 'Too many inquiry request per hour.',
            self::SYSTEM_TEMP_OFFLINE->value => 'System is temporarily offline.',
            self::INVALID_CONTENT_TYPE->value => '"Content-Type" header not defined as it should be.',
            self::INTERNAL_ERROR->value => 'Internal Error.',
            default => 'Unknown error code',
        };
    }
}
