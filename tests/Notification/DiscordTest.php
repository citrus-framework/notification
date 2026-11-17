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
use Citrus\Notification\Field;
use Citrus\Notification\Level;
use Citrus\Notification\NotificationException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Discord の送信先のテスト(組み込みサーバーに実際に送る)
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
     * 送信先を作る
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
    public function send_タイトルを渡すと枠のタイトルに入る()
    {
        $this->discord()->send('文', Level::SUCCESS, '[EventImport] 成功');

        $embed = json_decode($this->received()['body'], true)['embeds'][0];
        $this->assertSame('[EventImport] 成功', $embed['title']);
        $this->assertSame('文', $embed['description']);
    }

    #[Test]
    public function send_タイトルを渡さないとtitleのキーが無い()
    {
        $this->discord()->send('文', Level::INFO);

        $embed = json_decode($this->received()['body'], true)['embeds'][0];
        $this->assertArrayNotHasKey('title', $embed);
    }

    #[Test]
    public function send_空のタイトルでもtitleのキーが無い()
    {
        $this->discord()->send('文', Level::INFO, '');

        $embed = json_decode($this->received()['body'], true)['embeds'][0];
        $this->assertArrayNotHasKey('title', $embed);
    }

    #[Test]
    public function send_256文字ちょうどのタイトルはそのまま送られる()
    {
        $title = str_repeat('あ', 256);

        $this->discord()->send('文', Level::INFO, $title);

        $embed = json_decode($this->received()['body'], true)['embeds'][0];
        $this->assertSame($title, $embed['title']);
    }

    #[Test]
    public function send_256文字を超えるタイトルは頭を残して切り終わりに省略を付けて256文字に収める()
    {
        $this->discord()->send('文', Level::INFO, str_repeat('あ', 257));

        $title = json_decode($this->received()['body'], true)['embeds'][0]['title'];
        $this->assertSame(256, mb_strlen($title));
        $this->assertStringEndsWith(Discord::TRUNCATED_SUFFIX, $title);
        $this->assertSame(
            str_repeat('あ', 256 - mb_strlen(Discord::TRUNCATED_SUFFIX)) . Discord::TRUNCATED_SUFFIX,
            $title
        );
    }

    #[Test]
    public function send_空の文ではdescriptionのキーが無い()
    {
        $this->discord()->send('', Level::INFO, 'タイトル');

        $embed = json_decode($this->received()['body'], true)['embeds'][0];
        $this->assertArrayNotHasKey('description', $embed);
        $this->assertSame('タイトル', $embed['title']);
    }

    #[Test]
    public function send_フィールドは渡した順にfieldsへ入りinlineの既定はtrue()
    {
        $fields = [
            new Field('account', '0f3c'),
            new Field('count', '3', false),
            new Field('state', 'ok', true),
        ];

        $this->discord()->send('文', Level::INFO, null, $fields);

        $embed = json_decode($this->received()['body'], true)['embeds'][0];
        $this->assertSame(
            [
                ['name' => 'account', 'value' => '0f3c', 'inline' => true],
                ['name' => 'count', 'value' => '3', 'inline' => false],
                ['name' => 'state', 'value' => 'ok', 'inline' => true],
            ],
            $embed['fields']
        );
    }

    #[Test]
    public function send_枠のキーの順はtitle_description_fields_color()
    {
        $this->discord()->send('文', Level::INFO, 'タイトル', [new Field('a', 'b')]);

        $embed = json_decode($this->received()['body'], true)['embeds'][0];
        $this->assertSame(['title', 'description', 'fields', 'color'], array_keys($embed));
    }

    #[Test]
    public function send_フィールドが無いとfieldsのキーが無い()
    {
        $this->discord()->send('文', Level::INFO);

        $embed = json_decode($this->received()['body'], true)['embeds'][0];
        $this->assertArrayNotHasKey('fields', $embed);
    }

    #[Test]
    public function send_フィールドが25個を超えても減らさずに送る()
    {
        $fields = [];
        for ($i = 1; $i <= 26; $i++)
        {
            $fields[] = new Field('name' . $i, 'value' . $i);
        }

        $this->discord()->send('文', Level::INFO, null, $fields);

        $embed = json_decode($this->received()['body'], true)['embeds'][0];
        $this->assertCount(26, $embed['fields']);
        $this->assertSame('name26', $embed['fields'][25]['name']);
    }

    #[Test]
    public function send_空の名前と空の値はハイフンになる()
    {
        $this->discord()->send('文', Level::INFO, null, [new Field('', 'v'), new Field('n', ''), new Field('', '')]);

        $fields = json_decode($this->received()['body'], true)['embeds'][0]['fields'];
        $this->assertSame('-', $fields[0]['name']);
        $this->assertSame('v', $fields[0]['value']);
        $this->assertSame('n', $fields[1]['name']);
        $this->assertSame('-', $fields[1]['value']);
        $this->assertSame('-', $fields[2]['name']);
        $this->assertSame('-', $fields[2]['value']);
    }

    #[Test]
    public function send_名前256文字と値1024文字ちょうどはそのまま送られる()
    {
        $name = str_repeat('あ', 256);
        $value = str_repeat('い', 1024);

        $this->discord()->send('文', Level::INFO, null, [new Field($name, $value)]);

        $field = json_decode($this->received()['body'], true)['embeds'][0]['fields'][0];
        $this->assertSame($name, $field['name']);
        $this->assertSame($value, $field['value']);
    }

    #[Test]
    public function send_名前256文字と値1024文字を超えると切り終わりに省略を付けて上限に収める()
    {
        $this->discord()->send(
            '文',
            Level::INFO,
            null,
            [new Field(str_repeat('あ', 257), str_repeat('い', 1025))]
        );

        $field = json_decode($this->received()['body'], true)['embeds'][0]['fields'][0];
        $suffix_length = mb_strlen(Discord::TRUNCATED_SUFFIX);
        $this->assertSame(256, mb_strlen($field['name']));
        $this->assertStringEndsWith(Discord::TRUNCATED_SUFFIX, $field['name']);
        $this->assertSame(str_repeat('あ', 256 - $suffix_length) . Discord::TRUNCATED_SUFFIX, $field['name']);
        $this->assertSame(1024, mb_strlen($field['value']));
        $this->assertStringEndsWith(Discord::TRUNCATED_SUFFIX, $field['value']);
        $this->assertSame(str_repeat('い', 1024 - $suffix_length) . Discord::TRUNCATED_SUFFIX, $field['value']);
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
