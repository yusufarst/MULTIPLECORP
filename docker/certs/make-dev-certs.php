<?php

/*
 * Creates the local stack's TLS files once: a throwaway certificate authority and a
 * server certificate for localhost and the web service. Local development and CI only;
 * the files live in a Docker volume, never in the repository.
 */

$dir = $argv[1] ?? '/certs';

if (is_file("{$dir}/server.crt") && is_file("{$dir}/server.key") && is_file("{$dir}/ca.crt")) {
    fwrite(STDOUT, "Certificates exist in {$dir}.\n");
    exit(0);
}

$config = tempnam(sys_get_temp_dir(), 'openssl-');
file_put_contents($config, <<<'CNF'
[req]
distinguished_name = dn
[dn]
[ca]
basicConstraints = critical, CA:TRUE
keyUsage = critical, keyCertSign, cRLSign
subjectKeyIdentifier = hash
[server]
basicConstraints = critical, CA:FALSE
keyUsage = critical, digitalSignature, keyEncipherment
extendedKeyUsage = serverAuth
subjectAltName = DNS:localhost, DNS:web, IP:127.0.0.1
subjectKeyIdentifier = hash
authorityKeyIdentifier = keyid
CNF);

$keyOptions = ['private_key_type' => OPENSSL_KEYTYPE_EC, 'curve_name' => 'prime256v1'];

$caKey = openssl_pkey_new($keyOptions);
$caCsr = openssl_csr_new(['commonName' => 'MULTIPLECORP local development CA'], $caKey, ['config' => $config, 'digest_alg' => 'sha256']);
$ca = openssl_csr_sign($caCsr, null, $caKey, 825, ['config' => $config, 'digest_alg' => 'sha256', 'x509_extensions' => 'ca'], random_int(1, PHP_INT_MAX));

$serverKey = openssl_pkey_new($keyOptions);
$serverCsr = openssl_csr_new(['commonName' => 'localhost'], $serverKey, ['config' => $config, 'digest_alg' => 'sha256']);
$server = openssl_csr_sign($serverCsr, $ca, $caKey, 825, ['config' => $config, 'digest_alg' => 'sha256', 'x509_extensions' => 'server'], random_int(1, PHP_INT_MAX));

unlink($config);

if ($ca === false || $server === false) {
    fwrite(STDERR, 'Certificate creation failed: '.openssl_error_string()."\n");
    exit(1);
}

openssl_x509_export($ca, $caPem);
openssl_x509_export($server, $serverPem);
openssl_pkey_export($serverKey, $serverKeyPem);

file_put_contents("{$dir}/ca.crt", $caPem);
file_put_contents("{$dir}/server.crt", $serverPem.$caPem);
file_put_contents("{$dir}/server.key", $serverKeyPem);
chmod("{$dir}/ca.crt", 0644);
chmod("{$dir}/server.crt", 0644);
chmod("{$dir}/server.key", 0600);

fwrite(STDOUT, "Certificates written to {$dir}.\n");
