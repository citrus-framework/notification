<?php

declare(strict_types=1);

/**
 * @copyright   Copyright 2026, CitrusNotification. All Rights Reserved.
 * @author      take64 <take64@citrus.tk>
 * @license     http://www.citrus.tk/
 */

namespace Test;

use Citrus\Logger;
use Citrus\Logger\File;
use Citrus\Notification;
use Citrus\Notification\Discord;
use Citrus\Notification\Level;
use Citrus\Notification\NotificationException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Test\Fixture\FailDestination;
use Test\Fixture\PlainClass;
use Test\Fixture\RecordDestination;

/**
 * 知らせのテスト
 */
class NotificationTest extends TestCase
{
    /** @var string ログを書く一時的なフォルダ(末尾に / を付ける) */
    private string $logDirectory;

    protected function setUp(): void
    {
        $this->logDirectory = sprintf('%s/tmp-log-%s/', __DIR__, uniqid());
        mkdir($this->logDirectory, 0777, true);

        $this->configureLogger($this->logDirectory);
    }

    /**
     * logger の設定を読み込む(logger の状態は静的なので、読み込み直すと入れ替わる)
     */
    private function configureLogger(string $directory): void
    {
        Logger::sharedInstance()->loadConfigures([
            'logger' => [
                'type' => File::class,
                'directory' => $directory,
                'filename' => 'notification.log',
                'owner' => posix_getpwuid(posix_geteuid())['name'],
                'group' => posix_getgrgid(posix_getegid())['name'],
                'mode' => 0666,
            ],
        ]);
    }

    protected function tearDown(): void
    {
        // logger を元に戻す
        $this->configureLogger($this->logDirectory);
        foreach (glob($this->logDirectory . '*') as $file)
        {
            unlink($file);
        }
        rmdir($this->logDirectory);
    }

    /**
     * 書かれたログの全文
     */
    private function log(): string
    {
        $contents = '';
        foreach (glob($this->logDirectory . '*') as $file)
        {
            $contents .= file_get_contents($file);
        }
        return $contents;
    }

    #[Test]
    public function send_すべての送り先に送る()
    {
        $ops = new RecordDestination();
        $dev = new RecordDestination();

        (new Notification(['ops' => $ops, 'dev' => $dev]))->send('終わりました', Level::SUCCESS);

        $this->assertSame([['終わりました', Level::SUCCESS]], $ops->sent);
        $this->assertSame([['終わりました', Level::SUCCESS]], $dev->sent);
        $this->assertSame('', $this->log());
    }

    #[Test]
    public function send_重要度を省くとINFO()
    {
        $ops = new RecordDestination();

        (new Notification(['ops' => $ops]))->send('文');

        $this->assertSame([['文', Level::INFO]], $ops->sent);
    }

    #[Test]
    public function send_1つが失敗しても残りに送り例外は投げない()
    {
        $before = new RecordDestination();
        $after = new RecordDestination();
        $notification = new Notification([
            'before' => $before,
            'broken' => new FailDestination(),
            'after' => $after,
        ]);

        $notification->send('文', Level::ERROR);

        $this->assertCount(1, $before->sent);
        $this->assertCount(1, $after->sent);
    }

    #[Test]
    public function send_失敗は送り先の名前と例外の文でログに書かれる()
    {
        $notification = new Notification(['broken' => new FailDestination()]);

        $notification->send('文', Level::ERROR);

        $log = $this->log();
        $this->assertStringContainsString('broken', $log);
        $this->assertStringContainsString('送れませんでした 100% 失敗', $log);
    }

    #[Test]
    public function send_NotificationException以外の例外も捕まえてログに書く()
    {
        $after = new RecordDestination();
        $notification = new Notification([
            'broken' => new FailDestination(['notification' => false]),
            'after' => $after,
        ]);

        $notification->send('文');

        $this->assertStringContainsString('想定外の失敗', $this->log());
        $this->assertCount(1, $after->sent);
    }

    #[Test]
    public function send_ログに書けなくても例外は投げず成功する送り先には届く()
    {
        $after = new RecordDestination();
        $notification = new Notification(['broken' => new FailDestination(), 'after' => $after]);
        // ログに書けない状態にする(書けないときの PHP の警告は、このテストでは無視する)
        $this->configureLogger('/dev/null/notification/');
        set_error_handler(fn () => true);

        try
        {
            $notification->send('文', Level::ERROR);
        }
        finally
        {
            restore_error_handler();
        }

        $this->assertSame([['文', Level::ERROR]], $after->sent);
    }

    #[Test]
    public function send_送り先が0件なら何もしない()
    {
        (new Notification([]))->send('文', Level::ERROR);

        $this->assertSame('', $this->log());
    }

    #[Test]
    public function send_つながらなくてもログにWebhookのURLを入れず送り先の名前で示す()
    {
        $url = 'http://127.0.0.1:1/api/webhooks/secret-token';
        $notification = new Notification(['ops' => new Discord(['webhook_url' => $url])]);

        $notification->send('文');

        $log = $this->log();
        $this->assertStringContainsString('ops', $log);
        $this->assertStringNotContainsString('secret-token', $log);
        $this->assertStringNotContainsString($url, $log);
    }

    #[Test]
    public function fromConfigures_設定から送り先が作られる()
    {
        $notification = Notification::fromConfigures([
            'notification' => [
                'destinations' => [
                    'ops' => ['type' => RecordDestination::class, 'webhook_url' => 'a'],
                    'dev' => ['type' => RecordDestination::class, 'webhook_url' => 'b'],
                ],
            ],
        ]);

        $destinations = (new \ReflectionProperty($notification, 'destinations'))->getValue($notification);
        $this->assertSame(['ops', 'dev'], array_keys($destinations));
        $this->assertInstanceOf(RecordDestination::class, $destinations['ops']);
        $this->assertSame(['type' => RecordDestination::class, 'webhook_url' => 'a'], $destinations['ops']->configure);
        $this->assertSame(['type' => RecordDestination::class, 'webhook_url' => 'b'], $destinations['dev']->configure);
    }

    #[Test]
    public function fromConfigures_ドメインごとの設定で送り先1つの項目だけを上書きできる()
    {
        $notification = Notification::fromConfigures([
            'default' => [
                'notification' => [
                    'destinations' => [
                        'ops' => ['type' => RecordDestination::class, 'webhook_url' => 'default-ops'],
                        'dev' => ['type' => RecordDestination::class, 'webhook_url' => 'default-dev'],
                    ],
                ],
            ],
            // テストのドメインは example.com(Configurable の UNIT_TEST の決まり)
            'example.com' => [
                'notification' => [
                    'destinations' => [
                        'ops' => ['webhook_url' => 'domain-ops'],
                    ],
                ],
            ],
        ]);

        $destinations = (new \ReflectionProperty($notification, 'destinations'))->getValue($notification);
        $this->assertSame(['ops', 'dev'], array_keys($destinations));
        $this->assertSame(
            ['type' => RecordDestination::class, 'webhook_url' => 'domain-ops'],
            $destinations['ops']->configure
        );
        $this->assertSame(
            ['type' => RecordDestination::class, 'webhook_url' => 'default-dev'],
            $destinations['dev']->configure
        );
    }

    #[Test]
    public function fromConfigures_Discordも作れる()
    {
        $notification = Notification::fromConfigures([
            'notification' => [
                'destinations' => [
                    'ops' => ['type' => Discord::class, 'webhook_url' => 'https://discord.com/api/webhooks/1/x'],
                ],
            ],
        ]);

        $destinations = (new \ReflectionProperty($notification, 'destinations'))->getValue($notification);
        $this->assertInstanceOf(Discord::class, $destinations['ops']);
    }

    #[Test]
    public function fromConfigures_notificationの設定が無いと送り先は0件で何も送らない()
    {
        $notification = Notification::fromConfigures([]);

        $notification->send('文');

        $destinations = (new \ReflectionProperty($notification, 'destinations'))->getValue($notification);
        $this->assertSame([], $destinations);
        $this->assertSame('', $this->log());
    }

    #[Test]
    public function fromConfigures_destinationsが無いと送り先は0件()
    {
        $notification = Notification::fromConfigures(['notification' => []]);

        $destinations = (new \ReflectionProperty($notification, 'destinations'))->getValue($notification);
        $this->assertSame([], $destinations);
    }

    #[Test]
    public function fromConfigures_destinationsが空でも送り先は0件()
    {
        $notification = Notification::fromConfigures(['notification' => ['destinations' => []]]);

        $destinations = (new \ReflectionProperty($notification, 'destinations'))->getValue($notification);
        $this->assertSame([], $destinations);
    }

    #[Test]
    public function fromConfigures_typeが無いとNotificationException()
    {
        $this->expectException(NotificationException::class);
        $this->expectExceptionMessage('ops');

        Notification::fromConfigures(['notification' => ['destinations' => ['ops' => ['webhook_url' => 'a']]]]);
    }

    #[Test]
    public function fromConfigures_クラスが無いとNotificationException()
    {
        $this->expectException(NotificationException::class);
        $this->expectExceptionMessage('ops');

        Notification::fromConfigures(['notification' => ['destinations' => ['ops' => ['type' => 'No\\Such\\Class']]]]);
    }

    #[Test]
    public function fromConfigures_Destinationを実装していないとNotificationException()
    {
        $this->expectException(NotificationException::class);
        $this->expectExceptionMessage('ops');

        Notification::fromConfigures(['notification' => ['destinations' => ['ops' => ['type' => PlainClass::class]]]]);
    }

    #[Test]
    public function fromConfigures_送り先の設定が配列でないとNotificationException()
    {
        $this->expectException(NotificationException::class);

        Notification::fromConfigures(['notification' => ['destinations' => ['ops' => 'discord']]]);
    }
}
