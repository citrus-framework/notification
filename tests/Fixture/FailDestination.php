<?php

declare(strict_types=1);

/**
 * @copyright   Copyright 2026, CitrusNotification. All Rights Reserved.
 * @author      take64 <take64@citrus.tk>
 * @license     http://www.citrus.tk/
 */

namespace Test\Fixture;

use Citrus\Notification\Destination;
use Citrus\Notification\Level;
use Citrus\Notification\NotificationException;
use RuntimeException;

/**
 * テスト用の送り先(いつも例外を投げる)
 */
class FailDestination implements Destination
{
    /** @var bool true:NotificationException,false:NotificationException 以外の例外を投げる */
    private bool $notification;

    /**
     * {@inheritDoc}
     */
    public function __construct(array $configure = [])
    {
        $this->notification = ($configure['notification'] ?? true);
    }

    /**
     * {@inheritDoc}
     */
    public function send(string $text, Level $level): void
    {
        if ($this->notification === true)
        {
            throw new NotificationException('送れませんでした 100% 失敗');
        }
        throw new RuntimeException('想定外の失敗');
    }
}
