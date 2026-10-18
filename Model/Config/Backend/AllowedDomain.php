<?php
/**
 * Copyright © DMLab. All rights reserved.
 * Licensed under the Open Software License version 3.0 (OSL-3.0). See LICENSE.
 */
declare(strict_types=1);

namespace DmLab\AdminSsoGoogle\Model\Config\Backend;

use Magento\Framework\App\Config\Value;
use Magento\Framework\Exception\LocalizedException;

/**
 * Normalizes and validates the Allowed Workspace Domain on save.
 *
 * The domain is stored as a lowercase bare hostname and may only be empty while
 * Google is not the active, enabled provider (Google login fails closed without it).
 */
class AllowedDomain extends Value
{
    private const DOMAIN_PATTERN = '/^(?=.{1,253}$)(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,63}$/';

    private const GENERAL_GROUP = 'general';
    private const GENERAL_PATH = 'dmlab_admin_sso/general/';
    private const GOOGLE_CODE = 'google';

    /**
     * @inheritdoc
     *
     * @throws LocalizedException
     */
    public function beforeSave()
    {
        $value = strtolower(trim((string)$this->getValue()));

        if ($value !== '' && !preg_match(self::DOMAIN_PATTERN, $value)) {
            throw new LocalizedException(
                __('"%1" is not a valid domain. Enter a bare domain such as example.com.', $value)
            );
        }

        if ($value === ''
            && $this->getEffectiveGeneralValue('enabled') === '1'
            && $this->getEffectiveGeneralValue('active_provider') === self::GOOGLE_CODE
        ) {
            throw new LocalizedException(
                __('Allowed Workspace Domain is required while Google is the active identity provider.')
            );
        }

        $this->setValue($value);

        return parent::beforeSave();
    }

    /**
     * Value of an admin-sso `general` field: posted in this save, else stored (default scope).
     *
     * @param string $fieldId
     * @return string
     */
    private function getEffectiveGeneralValue(string $fieldId): string
    {
        $groups = $this->getData('groups');
        $posted = is_array($groups) ? ($groups[self::GENERAL_GROUP]['fields'][$fieldId] ?? null) : null;

        if (is_array($posted) && array_key_exists('value', $posted) && empty($posted['inherit'])) {
            return trim((string)$posted['value']);
        }

        return trim((string)$this->_config->getValue(self::GENERAL_PATH . $fieldId));
    }
}
