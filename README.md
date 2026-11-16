# Citrus Notification
php notification library

## Install

```
composer require citrus-framework/notification
```

## Usage

### 設定

案件の `citrus-configure.php` の `'notification'` に、送り先の名前を付けて列挙します。  
送り先の種類は `'type'` にクラス名で書きます。

```php
'notification' => [
    'destinations' => [
        'ops' => ['type' => \Citrus\Notification\Discord::class, 'webhook_url' => 'https://discord.com/api/webhooks/...'],
    ],
],
```

- 設定が無いとき、または送り先が1つも無いときは、何も送りません
- 送れなかった送り先は、送り先の名前と理由を citrus-framework/logger でログに書き、残りの送り先には送ります(例外は投げない)

### 送り方

```php
use Citrus\Notification;
use Citrus\Notification\Level;

Notification::fromConfigures($configures)->send('バッチが終わりました', Level::SUCCESS);
```

重要度は `Level::INFO`(既定)・`SUCCESS`・`WARNING`・`ERROR`。  
Discord では枠の色が変わります。
