<?php

namespace Tests\Unit\Services;

use App\Services\MediaVerificationService;
use DateTimeImmutable;
use Tests\TestCase;

class MediaVerificationServiceTest extends TestCase
{
    private DateTimeImmutable $now;

    protected function setUp(): void
    {
        parent::setUp();

        $this->now = new DateTimeImmutable('2026-08-19T10:00:00+00:00');
    }

    public function test_it_accepts_a_recent_trusted_digital_capture(): void
    {
        $result = $this->service()->evaluateReport($this->validReport(), $this->now);

        $this->assertTrue($result->isValid());
        $this->assertFalse($result->isToolUnavailable());
        $this->assertSame('verified', $result->reasonCode);
        $this->assertSame('urn:c2pa:test-manifest', $result->manifestId);
        $this->assertSame(
            '2026-08-19T09:55:00+00:00',
            $result->signingTime?->format(DATE_RFC3339),
        );
    }

    public function test_malformed_json_is_a_tool_output_failure_not_an_invalid_photo(): void
    {
        $result = $this->service()->evaluateJson('{not-json', $this->now);

        $this->assertTrue($result->isToolUnavailable());
        $this->assertSame('invalid_verifier_output', $result->reasonCode);
    }

    public function test_nonzero_process_exit_is_a_tool_failure_not_an_invalid_photo(): void
    {
        $temporaryDirectory = sys_get_temp_dir().DIRECTORY_SEPARATOR
            .'pairfect-c2pa-process-'.bin2hex(random_bytes(8));

        $this->assertTrue(mkdir($temporaryDirectory, 0700));

        $mediaPath = $temporaryDirectory.DIRECTORY_SEPARATOR.'report.php';
        $settingsPath = $temporaryDirectory.DIRECTORY_SEPARATOR.'settings.json';
        $trustPath = $temporaryDirectory.DIRECTORY_SEPARATOR.'trust.pem';

        file_put_contents(
            $mediaPath,
            '<?php fwrite(STDERR, "simulated verifier failure"); exit(7);',
        );
        file_put_contents($settingsPath, '{}');
        file_put_contents($trustPath, "test trust material\n");

        try {
            $service = new MediaVerificationService(configuration: [
                'binary' => PHP_BINARY,
                'settings' => $settingsPath,
                'trust_anchors' => $trustPath,
                'timeout_seconds' => 5,
                'max_output_bytes' => 65_536,
            ]);

            $result = $service->verify($mediaPath);

            $this->assertTrue($result->isToolUnavailable());
            $this->assertSame('verification_process_failed', $result->reasonCode);
        } finally {
            @unlink($mediaPath);
            @unlink($settingsPath);
            @unlink($trustPath);
            @rmdir($temporaryDirectory);
        }
    }

    public function test_c2patool_no_claim_exit_is_classified_as_an_unsigned_photo(): void
    {
        $temporaryDirectory = sys_get_temp_dir().DIRECTORY_SEPARATOR
            .'pairfect-c2pa-no-claim-'.bin2hex(random_bytes(8));

        $this->assertTrue(mkdir($temporaryDirectory, 0700));

        $mediaPath = $temporaryDirectory.DIRECTORY_SEPARATOR.'report.php';
        $settingsPath = $temporaryDirectory.DIRECTORY_SEPARATOR.'settings.json';
        $trustPath = $temporaryDirectory.DIRECTORY_SEPARATOR.'trust.pem';

        file_put_contents(
            $mediaPath,
            '<?php fwrite(STDERR, "Error: No claim found"); exit(1);',
        );
        file_put_contents($settingsPath, '{}');
        file_put_contents($trustPath, "test trust material\n");

        try {
            $service = new MediaVerificationService(configuration: [
                'binary' => PHP_BINARY,
                'settings' => $settingsPath,
                'trust_anchors' => $trustPath,
                'timeout_seconds' => 5,
                'max_output_bytes' => 65_536,
            ]);

            $result = $service->verify($mediaPath);

            $this->assertTrue($result->hasNoManifest());
            $this->assertFalse($result->isToolUnavailable());
            $this->assertSame('missing_active_manifest', $result->reasonCode);
        } finally {
            @unlink($mediaPath);
            @unlink($settingsPath);
            @unlink($trustPath);
            @rmdir($temporaryDirectory);
        }
    }

    public function test_a_different_exit_one_diagnostic_is_classified_as_invalid_embedded_c2pa(): void
    {
        $temporaryDirectory = sys_get_temp_dir().DIRECTORY_SEPARATOR
            .'pairfect-c2pa-invalid-asset-'.bin2hex(random_bytes(8));

        $this->assertTrue(mkdir($temporaryDirectory, 0700));
        $mediaPath = $temporaryDirectory.DIRECTORY_SEPARATOR.'report.php';
        $settingsPath = $temporaryDirectory.DIRECTORY_SEPARATOR.'settings.json';
        $trustPath = $temporaryDirectory.DIRECTORY_SEPARATOR.'trust.pem';

        file_put_contents($mediaPath, '<?php fwrite(STDERR, "manifest signature mismatch"); exit(1);');
        file_put_contents($settingsPath, '{}');
        file_put_contents($trustPath, "test trust material\n");

        try {
            $service = new MediaVerificationService(configuration: [
                'binary' => PHP_BINARY,
                'settings' => $settingsPath,
                'trust_anchors' => $trustPath,
                'timeout_seconds' => 5,
                'max_output_bytes' => 65_536,
            ]);

            $result = $service->verify($mediaPath);

            $this->assertTrue($result->isInvalid());
            $this->assertSame('c2pa_asset_rejected', $result->reasonCode);
        } finally {
            @unlink($mediaPath);
            @unlink($settingsPath);
            @unlink($trustPath);
            @rmdir($temporaryDirectory);
        }
    }

    public function test_it_rejects_a_report_without_an_active_manifest(): void
    {
        $report = $this->validReport();
        unset($report['active_manifest']);

        $result = $this->service()->evaluateReport($report, $this->now);

        $this->assertTrue($result->isInvalid());
        $this->assertSame('missing_active_manifest', $result->reasonCode);
    }

    public function test_it_rejects_a_manifest_without_a_signature(): void
    {
        $report = $this->validReport();
        unset($report['manifests']['urn:c2pa:test-manifest']['signature_info']);

        $result = $this->service()->evaluateReport($report, $this->now);

        $this->assertTrue($result->isInvalid());
        $this->assertSame('missing_signature', $result->reasonCode);
    }

    public function test_it_rejects_a_v1_claim_with_unreliable_timestamp_trust_results(): void
    {
        $report = $this->validReport();
        $report['manifests']['urn:c2pa:test-manifest']['claim_version'] = 1;

        $result = $this->service()->evaluateReport($report, $this->now);

        $this->assertTrue($result->isInvalid());
        $this->assertSame('unsupported_claim_version', $result->reasonCode);
    }

    public function test_it_rejects_any_top_level_validation_status(): void
    {
        $report = $this->validReport();
        $report['validation_status'][] = ['code' => 'claimSignature.mismatch'];

        $result = $this->service()->evaluateReport($report, $this->now);

        $this->assertTrue($result->isInvalid());
        $this->assertSame('validation_status_present', $result->reasonCode);
    }

    public function test_it_rejects_nested_validation_failures(): void
    {
        $report = $this->validReport();
        $report['validation_results']['ingredientDeltas'][] = [
            'validationDeltas' => [
                'failure' => [['code' => 'assertion.dataHash.mismatch']],
            ],
        ];

        $result = $this->service()->evaluateReport($report, $this->now);

        $this->assertTrue($result->isInvalid());
        $this->assertSame('validation_failure', $result->reasonCode);
    }

    public function test_it_rejects_a_cryptographically_valid_but_untrusted_manifest(): void
    {
        $report = $this->validReport();
        $report['validation_state'] = 'Valid';

        $result = $this->service()->evaluateReport($report, $this->now);

        $this->assertTrue($result->isInvalid());
        $this->assertSame('manifest_not_trusted', $result->reasonCode);
    }

    public function test_it_rejects_an_untrusted_signing_timestamp(): void
    {
        $report = $this->validReport();
        $report['validation_results']['activeManifest']['success'] = [
            ['code' => 'timeStamp.validated'],
        ];

        $result = $this->service()->evaluateReport($report, $this->now);

        $this->assertTrue($result->isInvalid());
        $this->assertSame('trusted_timestamp_missing', $result->reasonCode);
    }

    public function test_it_rejects_a_stale_signing_time(): void
    {
        $report = $this->validReport();
        $report['manifests']['urn:c2pa:test-manifest']['signature_info']['time'] =
            '2026-08-19T09:44:59+00:00';

        $result = $this->service()->evaluateReport($report, $this->now);

        $this->assertTrue($result->isInvalid());
        $this->assertSame('signing_time_too_old', $result->reasonCode);
    }

    public function test_it_rejects_a_signing_time_beyond_the_clock_skew(): void
    {
        $report = $this->validReport();
        $report['manifests']['urn:c2pa:test-manifest']['signature_info']['time'] =
            '2026-08-19T10:02:01+00:00';

        $result = $this->service()->evaluateReport($report, $this->now);

        $this->assertTrue($result->isInvalid());
        $this->assertSame('signing_time_in_future', $result->reasonCode);
    }

    public function test_it_rejects_a_manifest_without_a_digital_capture_action(): void
    {
        $report = $this->validReport();
        $report['manifests']['urn:c2pa:test-manifest']['assertions'][0]['data']['actions'][0]['digitalSourceType'] = 'http://cv.iptc.org/newscodes/digitalsourcetype/algorithmicMedia';

        $result = $this->service()->evaluateReport($report, $this->now);

        $this->assertTrue($result->isInvalid());
        $this->assertSame('digital_capture_action_missing', $result->reasonCode);
    }

    /** @return array<string, mixed> */
    private function validReport(): array
    {
        return [
            'active_manifest' => 'urn:c2pa:test-manifest',
            'manifests' => [
                'urn:c2pa:test-manifest' => [
                    'claim_version' => 2,
                    'signature_info' => [
                        'alg' => 'Es256',
                        'issuer' => 'Trusted Capture Device',
                        'time' => '2026-08-19T09:55:00+00:00',
                    ],
                    'assertions' => [
                        [
                            'label' => 'c2pa.actions.v2',
                            'data' => [
                                'actions' => [
                                    [
                                        'action' => 'c2pa.created',
                                        'digitalSourceType' => 'http://cv.iptc.org/newscodes/digitalsourcetype/digitalCapture',
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
            'validation_status' => [],
            'validation_results' => [
                'activeManifest' => [
                    'success' => [
                        ['code' => 'timeStamp.trusted'],
                        ['code' => 'timeStamp.validated'],
                        ['code' => 'claimSignature.validated'],
                    ],
                    'informational' => [],
                    'failure' => [],
                ],
                'ingredientDeltas' => [],
            ],
            'validation_state' => 'Trusted',
        ];
    }

    private function service(): MediaVerificationService
    {
        return new MediaVerificationService(configuration: [
            'max_signing_age_minutes' => 15,
            'future_skew_seconds' => 120,
            'require_trusted_timestamp' => true,
        ]);
    }
}
