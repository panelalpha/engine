<?php

namespace Tests\Unit\Ssl;

use App\Lib\Ssl\CertificateFacts;
use App\Lib\Ssl\CertificateStatus;
use PHPUnit\Framework\TestCase;

/**
 * Reading a real certificate, against two real certificates.
 *
 * Both are generated, self-signed and valid until 2036, and they differ in
 * exactly the thing that matters here: one carries a wildcard in its
 * subjectAltName and one does not. That difference is what decides whether
 * every project on a host can serve the engine's certificate or has to have
 * one of its own, so it is worth testing against openssl's actual output
 * rather than a hand-written array.
 */
class CertificateFactsTest extends TestCase
{
    private const WILD_CERT = <<<'PEM'
-----BEGIN CERTIFICATE-----
MIIDyjCCArKgAwIBAgIUE3iSt+kE4uioKo8aLHzGKAISHXowDQYJKoZIhvcNAQEL
BQAwTDELMAkGA1UEBhMCVVMxEzARBgNVBAoMClBhbmVsQWxwaGExKDAmBgNVBAMM
HzE3OC0xMDQtODQtNDUucGFuZWxhbHBoYS5kaXJlY3QwHhcNMjYwOTA2MTUwNzMx
WhcNMzYwOTAzMTUwNzMxWjBMMQswCQYDVQQGEwJVUzETMBEGA1UECgwKUGFuZWxB
bHBoYTEoMCYGA1UEAwwfMTc4LTEwNC04NC00NS5wYW5lbGFscGhhLmRpcmVjdDCC
ASIwDQYJKoZIhvcNAQEBBQADggEPADCCAQoCggEBAJxj5xALFLPlPSX9U0XDl3w3
p6EsQXUmk0XhY4vu7OkIVCo63sNok5PaCAHDgsveMc7V7MJW6GnhbvBR+ji06t3F
M+InsSFWcexpS2VJKJKkRiiCrL1s4gAUJ0THe3n4jVeAem7OF2/qsHcTIw1h6sYV
HyZTWlRgIUvUikHyPPb1ePAfw/5sRlk5Db5jDoUjdyJ3zdc3NtNDLd2RKllxMGtd
8zotLC2MU3cBArOz0MfWPBu9DJYIgJnF9ZCa+B5Wuu4rdABI3zOnuCqExEGZIZSk
Bi1Lm9Tk1JAHCruwpxvKuNl/0bcSQxJ5MnHShKKhu+74U2EogOfuE+6/bC7wYlMC
AwEAAaOBozCBoDAdBgNVHQ4EFgQULjm3UTOrd+VxmaTQPQOxhaCoKTAwHwYDVR0j
BBgwFoAULjm3UTOrd+VxmaTQPQOxhaCoKTAwDwYDVR0TAQH/BAUwAwEB/zBNBgNV
HREERjBEgh8xNzgtMTA0LTg0LTQ1LnBhbmVsYWxwaGEuZGlyZWN0giEqLjE3OC0x
MDQtODQtNDUucGFuZWxhbHBoYS5kaXJlY3QwDQYJKoZIhvcNAQELBQADggEBABn8
+11kdUqWc7/5kXxhGo/M23ht/BX4cLDDcUl66oiv4nx3YLqi7vtmDrd6xMN+vIxD
/fS477HQ/ElVk3gg9qYfktLNN1YjXUpYA3QI1CnFGBiYkyOE84LcrLkvHSH2Qumc
o/qa+PrZvMoMgaEpaqqrZQzet0SBraSLlGU9fRgAzSna51kUJPhOMWXKhNi+rz63
GlfVVkYVUjTpVSIzIkJnf5MQlz7RQwlVHLIXUonKDgpjOfGpa0UtSXOUcxxppiqD
Y603O5+sD3Ek31PXTZr+Wx43cDiGtVppP/c/s2zFS/tu/ZB3xJmBFZdGD9W5/Ak2
eMH8qXoNpLli8dXVqoE=
-----END CERTIFICATE-----
PEM;

    private const SINGLE_CERT = <<<'PEM'
-----BEGIN CERTIFICATE-----
MIIDpTCCAo2gAwIBAgIUMzHlrLG661TrBLvwYnA11YzZmrwwDQYJKoZIhvcNAQEL
BQAwTDELMAkGA1UEBhMCVVMxEzARBgNVBAoMClBhbmVsQWxwaGExKDAmBgNVBAMM
HzE3OC0xMDQtODQtNDUucGFuZWxhbHBoYS5kaXJlY3QwHhcNMjYwOTA2MTUwNzMx
WhcNMzYwOTAzMTUwNzMxWjBMMQswCQYDVQQGEwJVUzETMBEGA1UECgwKUGFuZWxB
bHBoYTEoMCYGA1UEAwwfMTc4LTEwNC04NC00NS5wYW5lbGFscGhhLmRpcmVjdDCC
ASIwDQYJKoZIhvcNAQEBBQADggEPADCCAQoCggEBAKbStqb2ZG9HtpSogA1DJPNl
ETHe53Sc3elzn9hdY9ouVatSUt6b7lVit735MpBLldwqqLHfMBbUSV6hLZ15QA9s
ZaGZMi+PHXAbyws/48ZqDjUd6Euo8ZNHuBasLtemGePCY14uTFW43qw5LIyl9/Fe
eHPc77L8lOf4HOEdTmhevK466KVy5Bp0MiRV7PKiYSB/Zr8Y96mUdwcyfpeNcG10
YjJCqDx1WHviQnMK3UU4uGvf3jfN+wutuHZb7fpgSiFUy5WLW3UW9QwnjCGeB2qJ
dqOKK5lLXlx/LdhR9Kj3S77Gg2lDjX0VNjrZMP4+cwh1d0O8OTIpCiqGWrIn6fEC
AwEAAaN/MH0wHQYDVR0OBBYEFOsTAc2TBDo5OmFFhV+/O63AUsnIMB8GA1UdIwQY
MBaAFOsTAc2TBDo5OmFFhV+/O63AUsnIMA8GA1UdEwEB/wQFMAMBAf8wKgYDVR0R
BCMwIYIfMTc4LTEwNC04NC00NS5wYW5lbGFscGhhLmRpcmVjdDANBgkqhkiG9w0B
AQsFAAOCAQEAloy0kGih5pDhwbptgTPZ3JZiXXNWtP1btzaDOqo5MWEwP7g5fewH
0upjCLtxOSiOzRRVid3yB086cxDwbDpwKjbICHAJDTsmMFh79XRtwXLHcj9KLtB6
fGhK3+DPegC3I963xXVDfXbloRHCCVELZnAounydbafnSazllIAG3QjKMay/iBLa
2/GwigAMok6+6W+iYDCzhhKUeKBXFnYVwpAn8+92TPb7n+3RsZ2bqfqS9b1/ZVos
tVuXGmFJgXNfsuVQfENQHqjWk9nCx20sEJtTLo2ikH9t4bu8MK/zTOuBSyz4TcB0
ahhUmsm25jPb1yHcKbUoUvO1qD0SvSAoRQ==
-----END CERTIFICATE-----
PEM;


    public function test_a_wildcard_certificate_reports_both_of_its_names(): void
    {
        $facts = CertificateFacts::fromPem(self::WILD_CERT);

        $this->assertNotNull($facts);
        $this->assertSame('178-104-84-45.panelalpha.direct', $facts['common_name']);
        $this->assertSame('PanelAlpha', $facts['issuer_name']);
        $this->assertSame(
            ['178-104-84-45.panelalpha.direct', '*.178-104-84-45.panelalpha.direct'],
            $facts['domains'],
            'the subjectAltName list is the certificate\'s real name list'
        );
        $this->assertIsInt($facts['not_before']);
        $this->assertIsInt($facts['not_after']);
    }

    /**
     * The whole point of the exercise: a wildcard covers every project on the
     * host, and the certificate without one covers only the engine itself.
     */
    public function test_only_the_wildcard_covers_a_project_domain(): void
    {
        $project = 'demo.178-104-84-45.panelalpha.direct';

        $wild = CertificateStatus::of(CertificateFacts::fromPem(self::WILD_CERT), $project);
        $single = CertificateStatus::of(CertificateFacts::fromPem(self::SINGLE_CERT), $project);

        $this->assertTrue($wild['covers_domain']);
        $this->assertFalse($single['covers_domain']);
        $this->assertSame(CertificateStatus::DOMAIN_MISMATCH, $single['status']);
    }

    public function test_both_cover_the_engine_name_itself(): void
    {
        $engine = '178-104-84-45.panelalpha.direct';

        foreach ([self::WILD_CERT, self::SINGLE_CERT] as $pem) {
            $status = CertificateStatus::of(CertificateFacts::fromPem($pem), $engine);
            $this->assertTrue($status['covers_domain']);
            $this->assertSame(CertificateStatus::SELF_SIGNED, $status['status'], 'both fixtures are self-signed');
        }
    }

    /**
     * A wildcard is one label deep, which is a constraint on how project
     * domains may ever be named.
     */
    public function test_a_wildcard_does_not_reach_two_labels_deep(): void
    {
        $status = CertificateStatus::of(
            CertificateFacts::fromPem(self::WILD_CERT),
            'a.b.178-104-84-45.panelalpha.direct'
        );

        $this->assertFalse($status['covers_domain']);
    }

    public function test_something_that_is_not_a_certificate_reads_as_nothing(): void
    {
        $this->assertNull(CertificateFacts::fromPem(''));
        $this->assertNull(CertificateFacts::fromPem('   '));
        $this->assertNull(CertificateFacts::fromPem('-----BEGIN CERTIFICATE-----\nnot base64\n-----END CERTIFICATE-----'));
        $this->assertNull(CertificateFacts::fromPem(self::WILD_CERT . 'trailing junk'));
    }
}
