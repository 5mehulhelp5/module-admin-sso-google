<?php
/**
 * Copyright © DMLab. All rights reserved.
 * Licensed under the Open Software License version 3.0 (OSL-3.0). See LICENSE.
 */
declare(strict_types=1);

namespace DmLab\AdminSsoGoogle\Test\Unit\Model\Config\Backend;

use DmLab\AdminSsoGoogle\Model\Config\Backend\AllowedDomain;
use Magento\Framework\App\Cache\TypeListInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Event\ManagerInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Model\Context;
use Magento\Framework\Registry;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class AllowedDomainTest extends TestCase
{
    /**
     * @param string $value
     * @param array<string,string> $stored admin-sso general field id => stored value
     * @param array<string,mixed>|null $groups posted section groups
     */
    private function model(string $value, array $stored = [], ?array $groups = null): AllowedDomain
    {
        $scopeConfig = $this->createStub(ScopeConfigInterface::class);
        $scopeConfig->method('getValue')->willReturnCallback(
            static fn (string $path) => $stored[substr($path, strlen('dmlab_admin_sso/general/'))] ?? null
        );

        $context = $this->createStub(Context::class);
        $context->method('getEventDispatcher')->willReturn($this->createStub(ManagerInterface::class));

        $model = new AllowedDomain(
            $context,
            $this->createStub(Registry::class),
            $scopeConfig,
            $this->createStub(TypeListInterface::class)
        );
        $model->setValue($value);
        if ($groups !== null) {
            $model->setData('groups', $groups);
        }

        return $model;
    }

    /**
     * @param array<string,string> $fields
     * @return array<string,mixed>
     */
    private static function postedGeneral(array $fields): array
    {
        $posted = [];
        foreach ($fields as $id => $value) {
            $posted[$id] = ['value' => $value];
        }

        return ['general' => ['fields' => $posted]];
    }

    public function testValidDomainIsTrimmedAndLowercased(): void
    {
        $model = $this->model('  Example.COM ');
        $model->beforeSave();

        self::assertSame('example.com', $model->getValue());
    }

    public function testSubdomainIsAccepted(): void
    {
        $model = $this->model('corp.example.co.uk');
        $model->beforeSave();

        self::assertSame('corp.example.co.uk', $model->getValue());
    }

    /**
     * @return array<string,array{string}>
     */
    public static function boundaryDomains(): array
    {
        return [
            '63-char label' => [str_repeat('a', 63) . '.com'],
            '253-char domain' => [str_repeat(str_repeat('a', 63) . '.', 3) . str_repeat('a', 57) . '.com'],
        ];
    }

    #[DataProvider('boundaryDomains')]
    public function testDomainAtLengthLimitIsAccepted(string $value): void
    {
        $model = $this->model($value);
        $model->beforeSave();

        self::assertSame($value, $model->getValue());
    }

    /**
     * @return array<string,array{string}>
     */
    public static function invalidDomains(): array
    {
        return [
            'scheme' => ['https://example.com'],
            'path' => ['example.com/path'],
            'email' => ['user@example.com'],
            'no tld' => ['localhost'],
            'leading dot' => ['.example.com'],
            'trailing dot' => ['example.com.'],
            'leading hyphen label' => ['-example.com'],
            'trailing hyphen label' => ['example-.com'],
            'space inside' => ['exa mple.com'],
            'wildcard' => ['*.example.com'],
            'numeric tld' => ['example.123'],
            'label too long' => [str_repeat('a', 64) . '.com'],
            'domain too long' => [str_repeat(str_repeat('a', 63) . '.', 3) . str_repeat('a', 58) . '.com'],
            'port' => ['example.com:443'],
        ];
    }

    #[DataProvider('invalidDomains')]
    public function testInvalidDomainIsRejected(string $value): void
    {
        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('is not a valid domain');

        $this->model($value)->beforeSave();
    }

    public function testEmptyRejectedWhenGoogleActiveAndEnabledInStoredConfig(): void
    {
        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('Allowed Workspace Domain is required');

        $this->model('  ', ['enabled' => '1', 'active_provider' => 'google'])->beforeSave();
    }

    public function testEmptyRejectedWhenGoogleActiveAndEnabledInPostedGroups(): void
    {
        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('Allowed Workspace Domain is required');

        $this->model(
            '',
            ['enabled' => '0', 'active_provider' => 'okta'],
            self::postedGeneral(['enabled' => '1', 'active_provider' => 'google'])
        )->beforeSave();
    }

    public function testEmptyRejectedWhenGooglePostedAndEnabledStored(): void
    {
        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('Allowed Workspace Domain is required');

        $this->model(
            '',
            ['enabled' => '1', 'active_provider' => 'okta'],
            self::postedGeneral(['active_provider' => 'google'])
        )->beforeSave();
    }

    public function testPostedInheritFallsBackToStoredConfig(): void
    {
        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('Allowed Workspace Domain is required');

        $this->model(
            '',
            ['enabled' => '1', 'active_provider' => 'google'],
            ['general' => ['fields' => [
                'enabled' => ['inherit' => '1'],
                'active_provider' => ['value' => 'okta', 'inherit' => '1'],
            ]]]
        )->beforeSave();
    }

    public function testEmptyAcceptedWhenAnotherProviderIsActive(): void
    {
        $model = $this->model(
            '',
            ['enabled' => '1', 'active_provider' => 'google'],
            self::postedGeneral(['enabled' => '1', 'active_provider' => 'okta'])
        );
        $model->beforeSave();

        self::assertSame('', $model->getValue());
    }

    public function testEmptyAcceptedWhenSsoIsDisabled(): void
    {
        $model = $this->model(
            '',
            ['enabled' => '1', 'active_provider' => 'google'],
            self::postedGeneral(['enabled' => '0'])
        );
        $model->beforeSave();

        self::assertSame('', $model->getValue());
    }

    public function testEmptyAcceptedWhenNothingIsConfigured(): void
    {
        $model = $this->model('');
        $model->beforeSave();

        self::assertSame('', $model->getValue());
    }
}
