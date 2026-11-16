<?php

declare(strict_types=1);

/**
 * @copyright   Copyright 2026, CitrusNotification. All Rights Reserved.
 * @author      take64 <take64@citrus.tk>
 * @license     http://www.citrus.tk/
 */

namespace Citrus\Notification;

/**
 * 送り先(送り先の種類のクラスが実装する)
 */
interface Destination
{
    /**
     * constructor.
     * @param array $configure 設定の送り先1つ分の配列
     * @throws NotificationException
     */
    public function __construct(array $configure);

    /**
     * 知らせを送る
     * @param string $text  文
     * @param Level  $level 重要度
     * @return void
     * @throws NotificationException 送れなかったとき
     */
    public function send(string $text, Level $level): void;
}
