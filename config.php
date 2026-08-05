<?php
// ======== LUXE WAVE 全体設定 ========

// 1. SwitchBot デバイスとAPIキーの設定
// 複数のアカウントのプラグをそれぞれ登録できます
$devicesConfig = [
    // 1台目（あなたのアカウントのプラグ）
    [
        'deviceId' => 'F85B1B276B1A',
        'token'    => '951c3f3e050dc345cbbc5fe37014b6e1762610a5dc40869e27eccae567c115c45224ef77ea1b6ce64ec34bc0f5be6446',
        'secret'   => '755b8f92c7116e2e042545b360c0427d'
    ],
    // 2台目（仲間の方のアカウントのプラグ）
    [
        'deviceId' => '588C81B83CA6',
        'token'    => '17dad68a45d47f916ba9eaaa3099f22982b0789f64c798a80413cffa836f13a47a5184b687e76b2913355f96ef68f75a',
        'secret'   => 'f008e310076994dc069ea02d49959a3e'
    ],
    // 3台目（仲間の方の2つ目のプラグ）
    [
        'deviceId' => '806599380CDE',
        'token'    => '17dad68a45d47f916ba9eaaa3099f22982b0789f64c798a80413cffa836f13a47a5184b687e76b2913355f96ef68f75a',
        'secret'   => 'f008e310076994dc069ea02d49959a3e'
    ]
];
?>