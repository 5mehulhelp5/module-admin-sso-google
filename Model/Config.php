<?php
/**
 * Copyright © DMLab. All rights reserved.
 * Licensed under the Open Software License version 3.0 (OSL-3.0). See LICENSE.
 */
declare(strict_types=1);

namespace DmLab\AdminSsoGoogle\Model;

use Magento\Framework\App\Config\ScopeConfigInterface;

/**
 * Typed reader over the Google-specific admin configuration.
 *
 * Google is standard OIDC, so the only IdP-specific setting is the required
 * allowed Workspace domain, enforced against the `hd` claim on callback
 * ({@see \DmLab\AdminSsoGoogle\Plugin\EnforceHostedDomain}). It lives
 * under the admin-sso section (group `google`). The OIDC client id/secret are
 * shared across providers and read from admin-sso's own `general` config, not
 * re-declared here.
 */
class Config
{
    /** Required Workspace domain restricting who may sign in (the `hd` claim). */
    public const XML_PATH_ALLOWED_DOMAIN = 'dmlab_admin_sso/google/allowed_domain';

    /**
     * @param ScopeConfigInterface $scopeConfig
     */
    public function __construct(
        private readonly ScopeConfigInterface $scopeConfig
    ) {
    }

    /**
     * Configured allowed Workspace domain, or null when unset.
     *
     * Null means Google sign-in is misconfigured and every login is refused.
     * Trimmed; a blank value reads as null.
     */
    public function getAllowedDomain(): ?string
    {
        $value = $this->scopeConfig->getValue(self::XML_PATH_ALLOWED_DOMAIN);
        $value = is_string($value) ? trim($value) : '';

        return $value === '' ? null : $value;
    }
}
