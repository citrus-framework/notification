<?php

declare(strict_types=1);

/**
 * @copyright   Copyright 2026, CitrusNotification. All Rights Reserved.
 * @author      take64 <take64@citrus.tk>
 * @license     http://www.citrus.tk/
 */

namespace Citrus\Notification;

use Citrus\Http\Client;
use Citrus\Http\Client\BodyType;
use Citrus\Http\Client\Request;
use Citrus\Http\HttpException;
use Citrus\Http\MethodType;

/**
 * Discord の Webhook への送り先
 */
class Discord implements Destination
{
    /** 枠の色(灰) */
    public const COLOR_INFO = 0x95A5A6;

    /** 枠の色(緑) */
    public const COLOR_SUCCESS = 0x2ECC71;

    /** 枠の色(黄) */
    public const COLOR_WARNING = 0xF1C40F;

    /** 枠の色(赤) */
    public const COLOR_ERROR = 0xE74C3C;

    /** 枠の中の文の上限文字数 */
    public const DESCRIPTION_LIMIT = 4096;

    /** 切ったときに終わりに付ける文 */
    public const TRUNCATED_SUFFIX = '…（以下省略）';

    /** タイムアウト秒数 */
    public const TIMEOUT = 5;

    /** @var string Webhook の URL */
    private string $webhookUrl;

    /**
     * {@inheritDoc}
     */
    public function __construct(array $configure)
    {
        $webhook_url = ($configure['webhook_url'] ?? '');
        NotificationException::exceptionIf(
            (is_string($webhook_url) === false or $webhook_url === ''),
            'Discord の設定に webhook_url がありません'
        );
        $this->webhookUrl = $webhook_url;
    }

    /**
     * {@inheritDoc}
     * @throws HttpException
     */
    public function send(string $text, Level $level): void
    {
        $body = [
            'embeds' => [
                [
                    'description' => $this->truncate($text),
                    'color' => $this->color($level),
                ],
            ],
            'allowed_mentions' => [
                'parse' => [],
            ],
        ];
        $request = (new Request($this->webhookUrl, $body, MethodType::POST))
            ->setBodyType(BodyType::JSON)
            ->setTimeout(self::TIMEOUT);

        $status = Client::request($request)->status;
        NotificationException::exceptionIf(
            ($status < 200 or $status >= 300),
            sprintf('Discord に送れませんでした(ステータスコード %d)', $status)
        );
    }

    /**
     * 重要度に合う枠の色
     * @param Level $level 重要度
     * @return int
     */
    private function color(Level $level): int
    {
        return match ($level)
        {
            Level::INFO => self::COLOR_INFO,
            Level::SUCCESS => self::COLOR_SUCCESS,
            Level::WARNING => self::COLOR_WARNING,
            Level::ERROR => self::COLOR_ERROR,
        };
    }

    /**
     * 上限を超える文を、頭を残して切る(付ける文を含めて上限に収める)
     * @param string $text 文
     * @return string
     */
    private function truncate(string $text): string
    {
        if (mb_strlen($text) <= self::DESCRIPTION_LIMIT)
        {
            return $text;
        }

        $head_length = (self::DESCRIPTION_LIMIT - mb_strlen(self::TRUNCATED_SUFFIX));
        return mb_substr($text, 0, $head_length) . self::TRUNCATED_SUFFIX;
    }
}
