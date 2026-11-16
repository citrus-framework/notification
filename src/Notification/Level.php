<?php

declare(strict_types=1);

/**
 * @copyright   Copyright 2026, CitrusNotification. All Rights Reserved.
 * @author      take64 <take64@citrus.tk>
 * @license     http://www.citrus.tk/
 */

namespace Citrus\Notification;

/**
 * 重要度
 */
enum Level
{
    case INFO;
    case SUCCESS;
    case WARNING;
    case ERROR;
}
