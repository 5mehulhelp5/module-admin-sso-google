<?php
/**
 * Copyright © DMLab. All rights reserved.
 * Licensed under the Open Software License version 3.0 (OSL-3.0). See LICENSE.
 */
declare(strict_types=1);

namespace DmLab\AdminSsoGoogle\Test\Unit;

use DmLab\AdminSsoGoogle\Model\Config;
use DmLab\AdminSsoGoogle\Model\Config\Backend\AllowedDomain;
use PHPUnit\Framework\TestCase;

/**
 * Asserts adminhtml/system.xml exposes the Google allowed-domain field under the
 * admin-sso section, at the exact path {@see Config} reads.
 */
class SystemConfigTest extends TestCase
{
    /** @var \DOMXPath */
    private \DOMXPath $xpath;

    protected function setUp(): void
    {
        $systemXml = dirname(__DIR__, 2) . '/etc/adminhtml/system.xml';
        self::assertFileExists($systemXml);

        $dom = new \DOMDocument();
        self::assertTrue($dom->load($systemXml));
        $this->xpath = new \DOMXPath($dom);
    }

    public function testGoogleGroupLivesUnderAdminSsoSection(): void
    {
        $groups = $this->xpath->query(
            "/config/system/section[@id='dmlab_admin_sso']/group[@id='google']"
        );

        self::assertNotNull($groups);
        self::assertSame(1, $groups->length);
    }

    public function testAllowedDomainFieldMapsToConfigPath(): void
    {
        $fields = $this->xpath->query(
            "/config/system/section[@id='dmlab_admin_sso']"
            . "/group[@id='google']/field[@id='allowed_domain']"
        );

        self::assertNotNull($fields);
        self::assertSame(1, $fields->length, 'Missing google field allowed_domain.');

        /** @var \DOMElement $field */
        $field = $fields->item(0);
        self::assertSame(
            0,
            $this->xpath->query('config_path', $field)->length,
            'Field allowed_domain has a config_path override; its path is no longer id-derived.'
        );

        $group = $field->parentNode;
        $section = $group->parentNode;
        $derivedPath = $section->getAttribute('id') . '/'
            . $group->getAttribute('id') . '/'
            . $field->getAttribute('id');

        self::assertSame(Config::XML_PATH_ALLOWED_DOMAIN, $derivedPath);
    }

    /**
     * Cross-group depends must be fully-qualified section/group/field paths;
     * Magento only wildcard-pads single-segment relative paths, so a two-segment
     * path resolves to a non-existent field and the show/hide toggle never binds.
     */
    public function testGoogleGroupDependsAreFullyQualified(): void
    {
        $ids = $this->xpath->query(
            "/config/system/section[@id='dmlab_admin_sso']"
            . "/group[@id='google']/depends/field/@id"
        );

        $paths = [];
        foreach ($ids as $attr) {
            $paths[] = $attr->value;
        }

        self::assertContains('dmlab_admin_sso/general/enabled', $paths);
        self::assertContains('dmlab_admin_sso/general/active_provider', $paths);
        foreach ($paths as $path) {
            self::assertSame(3, count(explode('/', $path)), "Depend '$path' is not fully qualified.");
        }
    }

    public function testAllowedDomainDeclaresBackendModelAndIsRequired(): void
    {
        $field = "/config/system/section[@id='dmlab_admin_sso']"
            . "/group[@id='google']/field[@id='allowed_domain']";

        self::assertSame(
            AllowedDomain::class,
            trim((string)$this->xpath->evaluate("string($field/backend_model)"))
        );
        self::assertStringContainsString(
            'required-entry',
            (string)$this->xpath->evaluate("string($field/validate)")
        );

        $comment = strtolower((string)$this->xpath->evaluate("string($field/comment)"));
        self::assertStringNotContainsString('allow any', $comment);
        self::assertStringContainsString('required', $comment);
    }
}
