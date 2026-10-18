<?php
/**
 * Copyright © DMLab. All rights reserved.
 * Licensed under the Open Software License version 3.0 (OSL-3.0). See LICENSE.
 */
declare(strict_types=1);

namespace DmLab\AdminSsoGoogle\Test\Unit\Model;

use DmLab\AdminSsoGoogle\Model\Config;
use DmLab\AdminSsoGoogle\Model\GooglePreset;
use DmLab\SsoCore\Api\AuthorizationParamsProviderInterface;
use DmLab\SsoCore\Api\ProviderPresetInterface;
use Magento\Framework\View\Asset\Repository as AssetRepository;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class GooglePresetTest extends TestCase
{
    private const RESERVED_PARAMS = [
        'response_type',
        'client_id',
        'redirect_uri',
        'scope',
        'state',
        'nonce',
        'code_challenge',
        'code_challenge_method',
    ];

    /** @var GooglePreset preset under test. */
    private GooglePreset $preset;

    protected function setUp(): void
    {
        $this->preset = $this->preset('example.com');
    }

    private function preset(?string $allowedDomain): GooglePreset
    {
        $assetRepository = $this->createStub(AssetRepository::class);
        $assetRepository->method('getUrl')
            ->willReturnCallback(
                static fn (string $fileId) => 'https://magento.loc/static/' . $fileId
            );

        $config = $this->createStub(Config::class);
        $config->method('getAllowedDomain')->willReturn($allowedDomain);

        return new GooglePreset($assetRepository, $config);
    }

    public function testImplementsProviderPresetContract(): void
    {
        self::assertInstanceOf(ProviderPresetInterface::class, $this->preset);
    }

    public function testExposesIdentity(): void
    {
        self::assertSame('google', $this->preset->getCode());
        self::assertSame('Google Workspace', $this->preset->getLabel());
    }

    public function testDiscoveryUrlIsFixedGoogleEndpoint(): void
    {
        self::assertSame(
            'https://accounts.google.com/.well-known/openid-configuration',
            $this->preset->buildDiscoveryUrl([])
        );
    }

    public function testDiscoveryUrlIgnoresPassedConfig(): void
    {
        self::assertSame(
            'https://accounts.google.com/.well-known/openid-configuration',
            $this->preset->buildDiscoveryUrl(['domain' => 'example.com', 'hd' => 'acme.com'])
        );
    }

    public function testDefaultScopesAreOpenidProfileEmail(): void
    {
        self::assertSame(
            ['openid', 'profile', 'email'],
            $this->preset->getDefaultScopes()
        );
    }

    public function testGroupsClaimIsNull(): void
    {
        self::assertNull($this->preset->getGroupsClaim());
    }

    public function testButtonLabel(): void
    {
        self::assertSame('Sign in with Google', $this->preset->getButtonLabel());
    }

    public function testButtonIconResolvesShippedGoogleLogo(): void
    {
        self::assertSame(
            'https://magento.loc/static/DmLab_AdminSsoGoogle::images/google.svg',
            $this->preset->getButtonIconUrl()
        );
    }

    public function testImplementsAuthorizationParamsProvider(): void
    {
        self::assertInstanceOf(AuthorizationParamsProviderInterface::class, $this->preset);
    }

    /**
     * @return array<string,array{0:array<string,mixed>}>
     */
    public static function anyConfig(): array
    {
        return [
            'empty' => [[]],
            'client id only' => [['client_id' => 'abc']],
            'conflicting hd' => [['hd' => 'other.com', 'domain' => 'other.com']],
        ];
    }

    /**
     * @param array<string,mixed> $config
     */
    #[DataProvider('anyConfig')]
    public function testAuthorizationParamsSendConfiguredDomainAsHd(array $config): void
    {
        self::assertSame(['hd' => 'example.com'], $this->preset->getAuthorizationParams($config));
    }

    public function testAuthorizationParamsEmptyWhenNoDomain(): void
    {
        self::assertSame([], $this->preset(null)->getAuthorizationParams(['client_id' => 'abc']));
    }

    public function testAuthorizationParamsUseNoReservedKeyAndOnlyStrings(): void
    {
        $params = $this->preset->getAuthorizationParams([]);

        self::assertSame([], array_intersect(array_keys($params), self::RESERVED_PARAMS));
        foreach ($params as $value) {
            self::assertIsString($value);
        }
    }
}
