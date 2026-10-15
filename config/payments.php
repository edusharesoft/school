<?php

/*
|--------------------------------------------------------------------------
| Online payment gateways (Part 5-2)
|--------------------------------------------------------------------------
| Credentials are entered per branch (Fees & accounts → Online payment settings) and stored encrypted.
| "secret" fields are never sent back to the browser. Endpoints follow each provider's current documentation;
| Nagad gives its merchant API base URLs with the merchant agreement, so they are editable.
*/

return [
    // Minutes a checkout stays open before it is treated as abandoned (the gateway is asked once more first).
    'expire_after_minutes' => 120,

    // A Bangla QR payment is confirmed by people (and bank statements), so its attempt stays open for days, not hours.
    'qr_expire_after_minutes' => 4320,

    'gateways' => [
        // Bangla QR (Bangladesh Bank national QR, EMV merchant-presented). Not a redirect gateway: the school pastes the QR text its bank
        // issued, the payer scans a QR with the exact amount in any bank / wallet app and sends the TrxID; the accounts office
        // confirms it (or the bank statement is uploaded and matched automatically). See BanglaQrGateway.
        'banglaqr' => [
            'label' => 'Bangla QR (pay from any bank or mobile banking app)',
            'short' => 'Bangla QR',
            'method' => 'online',
            'account' => 'bank',
            'refund' => false,
            'manual' => true,
            'fields' => [
                'qr_text' => ['label' => 'Text of the bank-issued Bangla QR', 'textarea' => true],
                'instructions' => ['label' => 'Extra instructions shown to the payer (optional)'],
            ],
        ],
        // Our own gateway (listed first: the default choice in the portals). https://github.com/mspmedisoft/shataj-payment-gateway-integration
        'shatajpay' => [
            'label' => 'ShatajPay (bKash, Nagad, cards and more)',
            'short' => 'ShatajPay',
            'method' => 'online',
            'account' => 'bank',
            'refund' => false,
            // No verification API yet: a "Complete" callback is held for the accounts office (see ShatajPayGateway).
            'confirmed_by_callback_only' => true,
            'fields' => [
                'username' => ['label' => 'App username'],
                'app_key' => ['label' => 'App key'],
                'gateway_id' => ['label' => 'Gateway ID'],
                'api_url' => ['label' => 'API base URL', 'default' => 'https://pay.shataj.com'],
                'password' => ['label' => 'App password', 'secret' => true],
                'app_secret' => ['label' => 'App secret', 'secret' => true],
            ],
        ],
        'sslcommerz' => [
            'label' => 'SSLCOMMERZ (cards, mobile banking, internet banking)',
            'short' => 'SSLCOMMERZ',
            'method' => 'online',
            'account' => 'bank',
            'refund' => true,
            'fields' => [
                'store_id' => ['label' => 'Store ID'],
                'store_passwd' => ['label' => 'Store password', 'secret' => true],
            ],
        ],
        'bkash' => [
            'label' => 'bKash (Tokenized Checkout)',
            'short' => 'bKash',
            'method' => 'bkash',
            'account' => 'mobile',
            'refund' => true,
            'fields' => [
                'username' => ['label' => 'Username'],
                'app_key' => ['label' => 'App key'],
                'password' => ['label' => 'Password', 'secret' => true],
                'app_secret' => ['label' => 'App secret', 'secret' => true],
            ],
        ],
        'nagad' => [
            'label' => 'Nagad (merchant checkout)',
            'short' => 'Nagad',
            'method' => 'nagad',
            'account' => 'mobile',
            'refund' => false,
            'fields' => [
                'merchant_id' => ['label' => 'Merchant ID'],
                'merchant_number' => ['label' => 'Merchant account number'],
                'server_ip' => ['label' => 'Server public IP (X-KM-IP-V4)'],
                'sandbox_url' => ['label' => 'Sandbox API base URL', 'default' => 'http://sandbox.mynagad.com:10080/remote-payment-gateway-1.0/api/dfs'],
                'live_url' => ['label' => 'Live API base URL', 'default' => 'https://api.mynagad.com/api/dfs'],
                'merchant_private_key' => ['label' => 'Merchant private key', 'secret' => true, 'textarea' => true],
                'nagad_public_key' => ['label' => 'Nagad public key', 'secret' => true, 'textarea' => true],
            ],
        ],
        'shurjopay' => [
            'label' => 'shurjoPay',
            'short' => 'shurjoPay',
            'method' => 'online',
            'account' => 'bank',
            'refund' => false,
            'fields' => [
                'username' => ['label' => 'Username'],
                'prefix' => ['label' => 'Order prefix'],
                'password' => ['label' => 'Password', 'secret' => true],
            ],
        ],
        'aamarpay' => [
            'label' => 'aamarPay',
            'short' => 'aamarPay',
            'method' => 'online',
            'account' => 'bank',
            'refund' => false,
            'fields' => [
                'store_id' => ['label' => 'Store ID'],
                'signature_key' => ['label' => 'Signature key', 'secret' => true],
            ],
        ],
    ],
];
