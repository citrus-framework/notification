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

/**
 * テスト用の送信先(送った中身をためる)
 */
class RecordDestination implements Destination
{
    /** @var array 設定 */
    public array $configure;

    /** @var array[] 送った中身 [[文, 重要度, タイトル, フィールド], ...] */
    public array $sent = [];

    /**
     * {@inheritDoc}
     */
    public function __construct(array $configure = [])
    {
        $this->configure = $configure;
    }

    /**
     * {@inheritDoc}
     */
    public function send(string $text, Level $level, ?string $title = null, array $fields = []): void
    {
        $this->sent[] = [$text, $level, $title, $fields];
    }
}
