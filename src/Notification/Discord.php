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
 * Discord の Webhook への送信先
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

    /** 枠のタイトルの上限文字数 */
    public const TITLE_LIMIT = 256;

    /** フィールドの名前の上限文字数 */
    public const FIELD_NAME_LIMIT = 256;

    /** フィールドの値の上限文字数 */
    public const FIELD_VALUE_LIMIT = 1024;

    /** 空の名前・値の代わりに入れる文字 */
    public const EMPTY_FIELD = '-';

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
    public function send(string $text, Level $level, ?string $title = null, array $fields = []): void
    {
        $embed = [];
        // 空のタイトルは、タイトルが無いときと同じに扱う
        if ($title !== null and $title !== '')
        {
            $embed['title'] = $this->truncate($title, self::TITLE_LIMIT);
        }
        // 空の文は、文の欄を付けない
        if ($text !== '')
        {
            $embed['description'] = $this->truncate($text, self::DESCRIPTION_LIMIT);
        }
        if (count($fields) > 0)
        {
            $embed['fields'] = [];
            foreach ($fields as $field)
            {
                $embed['fields'][] = [
                    'name' => $this->truncate($this->fillEmpty($field->name), self::FIELD_NAME_LIMIT),
                    'value' => $this->truncate($this->fillEmpty($field->value), self::FIELD_VALUE_LIMIT),
                    'inline' => $field->inline,
                ];
            }
        }
        $embed['color'] = $this->color($level);

        $body = [
            'embeds' => [
                $embed,
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
     * 空の文字列を、代わりの文字にする
     * @param string $text 名前または値
     * @return string
     */
    private function fillEmpty(string $text): string
    {
        return ($text === '' ? self::EMPTY_FIELD : $text);
    }

    /**
     * 上限を超える文を、頭を残して切る(付ける文を含めて上限に収める)
     * @param string $text  文
     * @param int    $limit 上限文字数
     * @return string
     */
    private function truncate(string $text, int $limit): string
    {
        if (mb_strlen($text) <= $limit)
        {
            return $text;
        }

        $head_length = ($limit - mb_strlen(self::TRUNCATED_SUFFIX));
        return mb_substr($text, 0, $head_length) . self::TRUNCATED_SUFFIX;
    }
}
