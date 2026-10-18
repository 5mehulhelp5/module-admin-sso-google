<?php
/**
 * Copyright © DMLab. All rights reserved.
 * Licensed under the Open Software License version 3.0 (OSL-3.0). See LICENSE.
 */
declare(strict_types=1);

namespace DmLab\AdminSsoGoogle\Plugin;

use DmLab\AdminSsoGoogle\Model\Config;
use DmLab\AdminSsoGoogle\Model\GooglePreset;
use DmLab\SsoCore\Api\ProviderPresetInterface;
use DmLab\SsoCore\Model\Oidc\IdentityFactory;
use Magento\Framework\Exception\LocalizedException;

/**
 * Enforces the required Workspace-domain restriction on a Google admin login.
 *
 * Google emits an `hd` (hosted-domain) claim for Workspace accounts. This
 * before-plugin rejects any login whose `hd` does not match the configured
 * domain — including personal Google accounts, which carry no `hd` at all — and
 * fails closed when no domain is configured. The `hd` claim is the only domain
 * authority; the email suffix is never trusted. It also requires a verified
 * email (`email_verified` true), which Workspace accounts always carry. It runs
 * at identity creation (the point where validated claims are still available)
 * and only for the Google preset, so other providers are untouched.
 *
 * Implemented as an interceptor rather than a core change: `sso-core` and
 * `admin-sso` stay provider-agnostic; the Google-specific policy lives entirely
 * in this plugin (the open/closed seam).
 */
class EnforceHostedDomain
{
    /**
     * @param Config $config
     */
    public function __construct(
        private readonly Config $config
    ) {
    }

    /**
     * Reject the login before an identity is built unless `hd` matches and the email is verified.
     *
     * No configured domain is a misconfiguration and refuses every Google login.
     * Only Google logins are checked; the plugin fires on every provider (the
     * factory is shared) but short-circuits unless the active preset is Google.
     * The comparison is case-insensitive, as DNS domains are.
     *
     * @param IdentityFactory $subject
     * @param array<string,mixed> $claims validated ID-token claims
     * @param ProviderPresetInterface $preset active provider preset
     * @return array{0: array<string,mixed>, 1: ProviderPresetInterface}
     * @throws LocalizedException when no domain is configured, the `hd` claim
     *         is missing or does not match it, or the email is not verified
     */
    public function beforeCreate(
        IdentityFactory $subject,
        array $claims,
        ProviderPresetInterface $preset
    ): array {
        if (!$preset instanceof GooglePreset) {
            return [$claims, $preset];
        }

        $allowedDomain = $this->config->getAllowedDomain();
        if ($allowedDomain === null) {
            throw new LocalizedException(
                __('Google sign-in is not configured: an Allowed Workspace Domain is required.')
            );
        }

        $hostedDomain = isset($claims['hd']) && is_scalar($claims['hd'])
            ? trim((string)$claims['hd'])
            : '';

        if ($hostedDomain === '' || strcasecmp($hostedDomain, $allowedDomain) !== 0) {
            throw new LocalizedException(
                __('Your Google account is not permitted to sign in to this admin panel.')
            );
        }

        if (!$this->isEmailVerified($claims)) {
            throw new LocalizedException(__('Your Google account email is not verified.'));
        }

        return [$claims, $preset];
    }

    /**
     * Same truth table as sso-core `IdentityInterface::isEmailVerified()`, applied to raw claims.
     *
     * @param array<string,mixed> $claims
     * @return bool
     */
    private function isEmailVerified(array $claims): bool
    {
        $email = isset($claims['email']) && is_scalar($claims['email']) ? trim((string)$claims['email']) : '';
        if ($email === '') {
            return false;
        }

        $verified = $claims['email_verified'] ?? null;

        return $verified === true || (is_string($verified) && strcasecmp($verified, 'true') === 0);
    }
}
