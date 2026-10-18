<?php
/**
 * Copyright © DMLab. All rights reserved.
 * Licensed under the Open Software License version 3.0 (OSL-3.0). See LICENSE.
 */
declare(strict_types=1);

namespace DmLab\AdminSsoGoogle\Test\Unit\Plugin;

use DmLab\AdminSsoGoogle\Model\Config;
use DmLab\AdminSsoGoogle\Model\GooglePreset;
use DmLab\AdminSsoGoogle\Plugin\EnforceHostedDomain;
use DmLab\SsoCore\Api\ProviderPresetInterface;
use DmLab\SsoCore\Model\Oidc\IdentityFactory;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\View\Asset\Repository as AssetRepository;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class EnforceHostedDomainTest extends TestCase
{
    /** @var IdentityFactory identity factory the plugin wraps (unused subject). */
    private IdentityFactory $subject;

    private const VERIFIED = ['email' => 'jane@example.com', 'email_verified' => true];

    private const NOT_PERMITTED = 'Your Google account is not permitted to sign in to this admin panel.';

    /** @var GooglePreset real Google preset the plugin matches on. */
    private GooglePreset $googlePreset;

    protected function setUp(): void
    {
        $this->subject = new IdentityFactory();
        $this->googlePreset = new GooglePreset(
            $this->createStub(AssetRepository::class),
            $this->createStub(Config::class)
        );
    }

    /**
     * @param string|null $allowedDomain
     */
    private function plugin(?string $allowedDomain): EnforceHostedDomain
    {
        $config = $this->createStub(Config::class);
        $config->method('getAllowedDomain')->willReturn($allowedDomain);

        return new EnforceHostedDomain($config);
    }

    /**
     * @return array<string, array{0: array<string,mixed>}>
     */
    public static function anyClaimsProvider(): array
    {
        return [
            'workspace account' => [['sub' => 'u1', 'hd' => 'example.com']],
            'personal account' => [['sub' => 'u1']],
        ];
    }

    /**
     * @param array<string,mixed> $claims
     */
    #[DataProvider('anyClaimsProvider')]
    public function testEmptyDomainRejectsEveryGoogleLogin(array $claims): void
    {
        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('an Allowed Workspace Domain is required');

        $this->plugin(null)->beforeCreate($this->subject, $claims, $this->googlePreset);
    }

    public function testMatchingHostedDomainIsAllowed(): void
    {
        $claims = ['sub' => 'u1', 'hd' => 'example.com'] + self::VERIFIED;
        $result = $this->plugin('example.com')->beforeCreate($this->subject, $claims, $this->googlePreset);

        self::assertSame([$claims, $this->googlePreset], $result);
    }

    public function testMatchingHostedDomainWithSurroundingWhitespaceIsAllowed(): void
    {
        $claims = ['sub' => 'u1', 'hd' => '  example.com  '] + self::VERIFIED;
        $result = $this->plugin('example.com')->beforeCreate($this->subject, $claims, $this->googlePreset);

        self::assertSame([$claims, $this->googlePreset], $result);
    }

    public function testHostedDomainMatchIsCaseInsensitive(): void
    {
        $claims = ['sub' => 'u1', 'hd' => 'Example.COM'] + self::VERIFIED;
        $result = $this->plugin('example.com')->beforeCreate($this->subject, $claims, $this->googlePreset);

        self::assertSame([$claims, $this->googlePreset], $result);
    }

    public function testMismatchedHostedDomainIsRejected(): void
    {
        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage(self::NOT_PERMITTED);

        $this->plugin('example.com')->beforeCreate(
            $this->subject,
            ['sub' => 'u1', 'hd' => 'evil.com'] + self::VERIFIED,
            $this->googlePreset
        );
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function lookalikeDomainProvider(): array
    {
        return [
            'suffix-extended' => ['example.com.evil.com'],
            'prefix-extended' => ['notexample.com'],
            'subdomain' => ['corp.example.com'],
        ];
    }

    #[DataProvider('lookalikeDomainProvider')]
    public function testLookalikeHostedDomainIsRejected(string $hostedDomain): void
    {
        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage(self::NOT_PERMITTED);

        $this->plugin('example.com')->beforeCreate(
            $this->subject,
            ['sub' => 'u1', 'hd' => $hostedDomain] + self::VERIFIED,
            $this->googlePreset
        );
    }

    public function testPersonalAccountWithoutHostedDomainIsRejected(): void
    {
        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage(self::NOT_PERMITTED);

        $this->plugin('example.com')->beforeCreate(
            $this->subject,
            ['sub' => 'u1'] + self::VERIFIED,
            $this->googlePreset
        );
    }

    public function testBlankHostedDomainClaimIsRejected(): void
    {
        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage(self::NOT_PERMITTED);

        $this->plugin('example.com')->beforeCreate(
            $this->subject,
            ['sub' => 'u1', 'hd' => '   '] + self::VERIFIED,
            $this->googlePreset
        );
    }

    public function testNonScalarHostedDomainClaimIsRejected(): void
    {
        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage(self::NOT_PERMITTED);

        $this->plugin('example.com')->beforeCreate(
            $this->subject,
            ['sub' => 'u1', 'hd' => ['example.com']] + self::VERIFIED,
            $this->googlePreset
        );
    }

    /**
     * @return array<string, array{0: mixed}>
     */
    public static function verifiedValueProvider(): array
    {
        return [
            'bool true' => [true],
            'string true' => ['true'],
            'upper-case string' => ['TRUE'],
        ];
    }

    #[DataProvider('verifiedValueProvider')]
    public function testVerifiedEmailIsAllowed(mixed $verified): void
    {
        $claims = ['sub' => 'u1', 'hd' => 'example.com', 'email' => 'jane@example.com', 'email_verified' => $verified];
        $result = $this->plugin('example.com')->beforeCreate($this->subject, $claims, $this->googlePreset);

        self::assertSame([$claims, $this->googlePreset], $result);
    }

    /**
     * @return array<string, array{0: array<string,mixed>}>
     */
    public static function unverifiedClaimsProvider(): array
    {
        $email = ['email' => 'jane@example.com'];

        return [
            'bool false' => [$email + ['email_verified' => false]],
            'string false' => [$email + ['email_verified' => 'false']],
            'integer 1' => [$email + ['email_verified' => 1]],
            'missing' => [$email],
            'array' => [$email + ['email_verified' => [true]]],
            'missing email' => [['email_verified' => true]],
            'blank email' => [['email' => '  ', 'email_verified' => true]],
        ];
    }

    /**
     * @param array<string,mixed> $claims
     */
    #[DataProvider('unverifiedClaimsProvider')]
    public function testUnverifiedEmailIsRejected(array $claims): void
    {
        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('Your Google account email is not verified.');

        $this->plugin('example.com')->beforeCreate(
            $this->subject,
            ['sub' => 'u1', 'hd' => 'example.com'] + $claims,
            $this->googlePreset
        );
    }

    public function testNonGooglePresetIsUntouchedEvenWithEmptyDomain(): void
    {
        $otherPreset = $this->createStub(ProviderPresetInterface::class);

        $claims = ['sub' => 'u1'];
        $result = $this->plugin(null)->beforeCreate($this->subject, $claims, $otherPreset);

        self::assertSame([$claims, $otherPreset], $result);
    }

    public function testNonGooglePresetIsNeverChecked(): void
    {
        $otherPreset = $this->createStub(ProviderPresetInterface::class);

        $claims = ['sub' => 'u1', 'email' => 'jane@example.com', 'email_verified' => false];
        $result = $this->plugin('example.com')->beforeCreate($this->subject, $claims, $otherPreset);

        self::assertSame([$claims, $otherPreset], $result);
    }

    public function testDiXmlRegistersPluginOnIdentityFactory(): void
    {
        $diXml = dirname(__DIR__, 3) . '/etc/di.xml';
        self::assertFileExists($diXml);

        $dom = new \DOMDocument();
        self::assertTrue($dom->load($diXml));

        $plugin = $this->findPluginOnType($dom, 'DmLab\\SsoCore\\Model\\Oidc\\IdentityFactory');

        self::assertNotNull($plugin, 'EnforceHostedDomain is not registered as a plugin on IdentityFactory.');
        self::assertSame(EnforceHostedDomain::class, ltrim(trim($plugin->getAttribute('type')), '\\'));
    }

    private function findPluginOnType(\DOMDocument $dom, string $typeName): ?\DOMElement
    {
        foreach ($dom->getElementsByTagName('type') as $type) {
            if (ltrim($type->getAttribute('name'), '\\') !== $typeName) {
                continue;
            }
            foreach ($type->getElementsByTagName('plugin') as $plugin) {
                if (ltrim($plugin->getAttribute('type'), '\\') === EnforceHostedDomain::class) {
                    return $plugin;
                }
            }
        }

        return null;
    }
}
