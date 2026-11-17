<?php

declare(strict_types=1);

/**
 * @copyright   Copyright 2026, CitrusNotification. All Rights Reserved.
 * @author      take64 <take64@citrus.tk>
 * @license     http://www.citrus.tk/
 */

namespace Citrus\Notification;

/**
 * フィールド(通知に付ける、名前と値の組)
 */
class Field
{
    /**
     * constructor.
     * @param string $name   名前
     * @param string $value  値
     * @param bool   $inline 横に並べるか(省いたら true)
     */
    public function __construct(
        public readonly string $name,
        public readonly string $value,
        public readonly bool $inline = true,
    ) {
    }
}
