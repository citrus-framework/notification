<?php

declare(strict_types=1);

/**
 * @copyright   Copyright 2026, CitrusNotification. All Rights Reserved.
 * @author      take64 <take64@citrus.tk>
 * @license     http://www.citrus.tk/
 */

namespace Citrus;

use Citrus\Notification\Destination;
use Citrus\Notification\Field;
use Citrus\Notification\Level;
use Citrus\Notification\NotificationException;
use Citrus\Notification\NotificationSetting;
use Throwable;

/**
 * 通知
 */
class Notification
{
    /** @var Destination[] 送信先の名前 => 送信先 */
    private array $destinations;

    /**
     * constructor.
     * @param Destination[] $destinations 送信先の名前 => 送信先
     */
    public function __construct(array $destinations)
    {
        $this->destinations = $destinations;
    }

    /**
     * 設定から作る
     * @param array $configures 設定値配列(全て)
     * @return self
     * @throws NotificationException
     */
    public static function fromConfigures(array $configures): self
    {
        $setting = (new NotificationSetting())->loadConfigures($configures);

        $destinations = [];
        foreach ($setting->configures['destinations'] as $name => $configure)
        {
            $destinations[$name] = self::createDestination((string)$name, $configure);
        }

        return new self($destinations);
    }

    /**
     * すべての送信先に通知を送る(送れなかった送信先はログに書き、例外は投げない)
     * @param string      $text  文
     * @param Level       $level 重要度
     * @param string|null $title タイトル(省けるときは null)
     * @param Field[]     $fields フィールド(省けるときは空の配列)
     * @return void
     */
    public function send(string $text, Level $level = Level::INFO, ?string $title = null, array $fields = []): void
    {
        foreach ($this->destinations as $name => $destination)
        {
            try
            {
                $destination->send($text, $level, $title, $fields);
            }
            catch (Throwable $e)
            {
                $this->logFailure((string)$name, $e);
            }
        }
    }

    /**
     * 送れなかったことをログに書く(ログに書けなかったときは、何もしない)
     * @param string    $name 送信先の名前
     * @param Throwable $e    送れなかった理由の例外
     * @return void
     */
    private function logFailure(string $name, Throwable $e): void
    {
        try
        {
            Logger::error('通知を送れませんでした 送信先:%s %s', $name, $e->getMessage());
        }
        catch (Throwable)
        {
            // 残りの送信先に送るため、握りつぶす
            return;
        }
    }

    /**
     * 設定の送信先1つ分から、送信先を作る
     * @param string $name      送信先の名前
     * @param mixed  $configure 設定の送信先1つ分
     * @return Destination
     * @throws NotificationException
     */
    private static function createDestination(string $name, mixed $configure): Destination
    {
        $type = null;
        if (is_array($configure) === true)
        {
            $type = ($configure['type'] ?? null);
        }

        NotificationException::exceptionIf(
            (is_string($type) === false or $type === ''),
            sprintf('送信先 %s に type がありません', $name)
        );
        NotificationException::exceptionElse(
            (class_exists($type) === true),
            sprintf('送信先 %s の type のクラスがありません', $name)
        );
        NotificationException::exceptionElse(
            (is_subclass_of($type, Destination::class) === true),
            sprintf('送信先 %s の type が Destination を実装していません', $name)
        );

        return new $type($configure);
    }
}
