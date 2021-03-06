<?php


namespace sgpublic\scit\tool\rsa;

require SCIT_EDU_TOOL_ROOT.'/unit/phpseclib/autoload.php';
use phpseclib\Crypt\RSA;

class RSAStaticUnit {
    private static string $private_key = '-----BEGIN RSA PRIVATE KEY-----
MIICXQIBAAKBgQCmBCWNtxeofYkH1e9GXKgszj4EcJojNvlesPDM201q+fiVf2X4
SWPNjdduRS19dq9Koq4Dz0ul3xV6E3ydCHl88qSa94fDGZa24UueYVYE0ytYuJcO
u164GlIfu48Rir0NXA2BfoQxMcSpMmLJt20rSg+EoP24zaj3ti78b1zJEwIDAQAB
AoGABhMUxLC0XufpAa5kSPDO/oS1ZDgyi6NRUJOs2/ISTR3EaMP2mTUmP7k27sP9
PCABnfuB3oXRQMp+4K6h2qUavNaUNvslZmdILcVBE0JJJYibnHClLFUhJGODZrF0
AJ2Nhaq+qAfr5pfKIXkuhm5i6WLKc/fVmIB7yU2UH8DAQAECQQDUPLy/wDPdU4Mr
DXyY3gmBMWRmWLPPmLqtM8YCN0uCblvlQnDDvIrfJLMvhPk1aJD2x3jb/U9MfB6j
K22yVDkTAkEAyD+PtscuOn4EzMjcCHqQKe11d9zjv9id6q+EHx0M9zY5A+O/9e4G
3Ocjj/9gh9paFxak05EjCZP/JaaJu/cwAQJBAI33atJhANBlknHz/YpLy9PNdDk5
0F1m7kf5P9QvpKTEqVe7j65+qe4FoI6CxihBn+ZTG7cbxDWHOP8wh5on2F0CQG4W
K27jb3Gup/rhDb4Hi0vRhLvBjt+AOci0dyEXunIJuCyAP573HYTB+VYHokztaIu6
4iCBcM6qMyHCvYO9cAECQQCNWJ9njEMyVZAXdVGMN+hDYhl0PsuD4h01CS+mkJj2
P+K9lN4SfWsw9Z3LpxhNKNKD/NDimLIX1n5swpK9vssr
-----END RSA PRIVATE KEY-----';

    public static function decodePublicEncode($data) {
        $rsa = new RSA();
        $rsa->loadKey(self::$private_key);
        $rsa->setEncryptionMode(RSA::ENCRYPTION_PKCS1);
        return $rsa->decrypt(base64_decode(rawurldecode($data)));
    }
}