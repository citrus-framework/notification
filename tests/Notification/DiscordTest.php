<?php

declare(strict_types=1);

/**
 * @copyright   Copyright 2026, CitrusNotification. All Rights Reserved.
 * @author      take64 <take64@citrus.tk>
 * @license     http://www.citrus.tk/
 */

namespace Test\Notification;

use Citrus\Http\HttpException;
use Citrus\Notification\Discord;
use Citrus\Notification\Level;
use Citrus\Notification\NotificationException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Discord の送り先のテスト(組み込みサーバーに実際に送る)
 */
class DiscordTest extends TestCase
{
    /** @var resource|null 組み込みサーバーのプロセス */
    private static $process = null;

    /** @var string 組み込みサーバーのURL */
    private static string $base = '';

    /** @var string 受け取った中身を書くファイル */
    private static string $record = '';

    public static function setUpBeforeClass(): void
    {
        // 空いているポートを探す
        $socket = stream_socket_server('tcp://127.0.0.1:0');
        $name = stream_socket_get_name($socket, false);
        fclose($socket);
        $port = (int)substr($name, strrpos($name, ':') + 1);

        self::$base = sprintf('http://127.0.0.1:%d', $port);
        self::$record = sprintf('%s/../tmp-discord-%s.json', __DIR__, uniqid());
        $command = [PHP_BINARY, '-S', sprintf('127.0.0.1:%d', $port), __DIR__ . '/../Fixture/server.php'];
        $descriptors = [0 => ['pipe', 'r'], 1 => ['file', '/dev/null', 'w'], 2 => ['file', '/dev/null', 'w']];
        $environments = array_merge(getenv(), ['NOTIFICATION_RECORD' => self::$record]);
        self::$process = proc_open($command, $descriptors, $pipes, null, $environments);

        // 起動待ち
        for ($i = 0; $i < 50; $i++)
        {
            $connection = @fsockopen('127.0.0.1', $port);
            if ($connection !== false)
            {
                fclose($connection);
                return;
            }
            usleep(100000);
        }
        self::fail('組み込みサーバーが起動しませんでした。');
    }

    public static function tearDownAfterClass(): void
    {
        if (is_resource(self::$process) === true)
        {
            proc_terminate(self::$process);
            proc_close(self::$process);
        }
        if (file_exists(self::$record) === true)
        {
            unlink(self::$record);
        }
    }

    protected function setUp(): void
    {
        if (file_exists(self::$record) === true)
        {
            unlink(self::$record);
        }
    }

    /**
     * 送り先を作る
     */
    private function discord(int $status = 204): Discord
    {
        return new Discord(['webhook_url' => sprintf('%s/hook/%d', self::$base, $status)]);
    }

    /**
     * サーバーが受け取った中身
     */
    private function received(): array
    {
        return json_decode(file_get_contents(self::$record), true);
    }

    public static function 重要度と色(): array
    {
        return [
            'INFO' => [Level::INFO, 0x95A5A6],
            'SUCCESS' => [Level::SUCCESS, 0x2ECC71],
            'WARNING' => [Level::WARNING, 0xF1C40F],
            'ERROR' => [Level::ERROR, 0xE74C3C],
        ];
    }

    #[Test]
    #[DataProvider('重要度と色')]
    public function send_重要度ごとの色で送られる(Level $level, int $color)
    {
        $this->discord()->send('こんにちは', $level);

        $received = $this->received();
        $this->assertSame('application/json', $received['content_type']);
        $body = json_decode($received['body'], true);
        $this->assertSame([['description' => 'こんにちは', 'color' => $color]], $body['embeds']);
    }

    #[Test]
    public function send_本文はembedsとallowed_mentionsだけで送り主の名前もアイコンも無い()
    {
        $this->discord()->send('<@123> を呼ぶ文', Level::INFO);

        $body = json_decode($this->received()['body'], true);
        $this->assertSame(['embeds', 'allowed_mentions'], array_keys($body));
        $this->assertSame(['parse' => []], $body['allowed_mentions']);
        $this->assertSame('<@123> を呼ぶ文', $body['embeds'][0]['description']);
        $this->assertStringContainsString('"allowed_mentions":{"parse":[]}', $this->received()['body']);
    }

    #[Test]
    public function send_4096文字ちょうどはそのまま送られる()
    {
        $text = str_repeat('あ', 4096);

        $this->discord()->send($text, Level::INFO);

        $body = json_decode($this->received()['body'], true);
        $this->assertSame($text, $body['embeds'][0]['description']);
    }

    #[Test]
    public function send_4096文字を超えると頭を残して切り終わりに省略を付けて4096文字に収める()
    {
        $text = str_repeat('あ', 4097);

        $this->discord()->send($text, Level::INFO);

        $description = json_decode($this->received()['body'], true)['embeds'][0]['description'];
        $this->assertSame(4096, mb_strlen($description));
        $this->assertSame(str_repeat('あ', 4096 - 7) . '…（以下省略）', $description);
    }

    #[Test]
    public function send_4xxはNotificationExceptionでステータスコードが入る()
    {
        $this->expectException(NotificationException::class);
        $this->expectExceptionMessage('404');

        $this->discord(404)->send('文', Level::INFO);
    }

    #[Test]
    public function send_5xxはNotificationExceptionでステータスコードが入る()
    {
        $this->expectException(NotificationException::class);
        $this->expectExceptionMessage('500');

        $this->discord(500)->send('文', Level::INFO);
    }

    #[Test]
    public function send_2xxなら例外にならない()
    {
        $this->discord(200)->send('文', Level::INFO);

        $this->assertFileExists(self::$record);
    }

    #[Test]
    public function send_例外の文にWebhookのURLを入れない()
    {
        try
        {
            $this->discord(500)->send('文', Level::INFO);
            $this->fail('例外が投げられませんでした。');
        }
        catch (NotificationException $e)
        {
            $this->assertStringNotContainsString(self::$base, $e->getMessage());
            $this->assertStringNotContainsString('/hook/', $e->getMessage());
        }
    }

    #[Test]
    public function construct_webhook_urlが無いと作れない()
    {
        $this->expectException(NotificationException::class);

        new Discord([]);
    }

    #[Test]
    public function construct_webhook_urlが空だと作れない()
    {
        $this->expectException(NotificationException::class);

        new Discord(['webhook_url' => '']);
    }

    #[Test]
    public function send_タイムアウトより長く待たされるとHttpException()
    {
        // 組み込みサーバーが眠っているあいだ、次のテストは待たされるので、このテストは最後に置く
        $url = sprintf('%s/hook/204?sleep=%d', self::$base, Discord::TIMEOUT + 1);

        $this->expectException(HttpException::class);

        (new Discord(['webhook_url' => $url]))->send('文', Level::INFO);
    }
}
