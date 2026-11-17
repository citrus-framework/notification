<?php

declare(strict_types=1);

/**
 * @copyright   Copyright 2026, CitrusNotification. All Rights Reserved.
 * @author      take64 <take64@citrus.tk>
 * @license     http://www.citrus.tk/
 */

namespace Citrus\Notification;

use Citrus\Configure\Configurable;

/**
 * 通知の設定
 */
class NotificationSetting extends Configurable
{
    /**
     * {@inheritDoc}
     */
    protected function configureKey(): string
    {
        return 'notification';
    }

    /**
     * {@inheritDoc}
     */
    protected function configureDefaults(): array
    {
        return [
            'destinations' => [],
        ];
    }

    /**
     * {@inheritDoc}
     */
    protected function configureRequires(): array
    {
        return [];
    }
}
