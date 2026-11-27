<?php
declare(strict_types=1);

require dirname(__DIR__, 3) . '/vendor/autoload.php';

use addon\hsx_ysepay\core\support\YsepayCrypto;

if (!extension_loaded('openssl')) {
    fwrite(STDERR, "SKIP: openssl extension is unavailable\n");
    exit(0);
}

$key = openssl_pkey_new([
    'private_key_bits' => 2048,
    'private_key_type' => OPENSSL_KEYTYPE_RSA,
]);
if ($key === false) {
    throw new RuntimeException('Unable to generate RSA key');
}
$csr = openssl_csr_new(['commonName' => 'ysepay-smoke.local'], $key, ['digest_alg' => 'sha256']);
$certificate = openssl_csr_sign($csr, null, $key, 1, ['digest_alg' => 'sha256']);
if ($certificate === false) {
    throw new RuntimeException('Unable to create certificate');
}
$pfx = '';
$password = 'smoke-password';
if (!openssl_pkcs12_export($certificate, $pfx, $key, $password)) {
    throw new RuntimeException('Unable to export PFX');
}
openssl_x509_export($certificate, $certificatePem);

$crypto = new YsepayCrypto(base64_encode($pfx), $password, $certificatePem);
$pemUploadCrypto = new YsepayCrypto(base64_encode($pfx), $password, base64_encode($certificatePem));
$payload = [
    'timeStamp' => '2026-07-24 12:30:45',
    'method' => 'order.createOrder',
    'charset' => 'utf-8',
    'reqId' => 'smoke260724123045',
    'certId' => 'test',
    'version' => '6.3',
];
$signature = $crypto->sign($payload);
if (!$crypto->verify($payload + ['sign' => $signature], $signature)) {
    throw new RuntimeException('RSA sign/verify failed');
}
if (!$pemUploadCrypto->verify($payload + ['sign' => $signature], $signature)) {
    throw new RuntimeException('Base64 PEM certificate parsing failed');
}

$aesKey = random_bytes(16);
$encryptedKey = base64_decode($crypto->encryptKey($aesKey), true);
$decryptedKey = '';
if ($encryptedKey === false || !openssl_private_decrypt($encryptedKey, $decryptedKey, $key, OPENSSL_PKCS1_PADDING)) {
    throw new RuntimeException('RSA key encryption failed');
}
if (!hash_equals($aesKey, $decryptedKey)) {
    throw new RuntimeException('RSA key roundtrip mismatch');
}

$business = ['orderId' => '202607241234567890', 'amount' => 1, 'note' => '测试'];
$encryptedBusiness = $crypto->encryptBusiness($business, $aesKey);
if ($crypto->decryptBusiness($encryptedBusiness, $aesKey) !== $business) {
    throw new RuntimeException('AES business roundtrip mismatch');
}

if ($crypto->canonicalize(['b' => '2', 'sign' => 'ignored', 'a' => '1']) !== 'a=1&b=2') {
    throw new RuntimeException('Canonical sort failed');
}

echo "OK: hsx_ysepay crypto smoke passed\n";
