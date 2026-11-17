# Citrus Notification
php notification library

## Install

```
composer require citrus-framework/notification
```

## Usage

### 設定

案件の `citrus-configure.php` の `'notification'` に、送信先の名前を付けて列挙します。  
送信先の種類は `'type'` にクラス名で書きます。

```php
'notification' => [
    'destinations' => [
        'ops' => ['type' => \Citrus\Notification\Discord::class, 'webhook_url' => 'https://discord.com/api/webhooks/...'],
    ],
],
```

- 設定が無いとき、または送信先が1つも無いときは、何も送りません
- 送れなかった送信先は、送信先の名前と理由を citrus-framework/logger でログに書き、残りの送信先には送ります(例外は投げない)

### 送り方

```php
use Citrus\Notification;
use Citrus\Notification\Level;

Notification::fromConfigures($configures)->send('バッチが終わりました', Level::SUCCESS);
```

重要度は `Level::INFO`(既定)・`SUCCESS`・`WARNING`・`ERROR`。  
Discord では枠の色が変わります。

タイトルを付けるときは、3つ目の引数に書きます。タイトルは省略することが可能です。

```php
Notification::fromConfigures($configures)->send('取り込みが終わりました', Level::SUCCESS, '[EventImport] 成功');
```

Discord では、タイトルが枠のタイトルの欄に出ます。タイトルは 256 文字までで、超えたぶんは切って終わりに `…（以下省略）` を付けます。  
タイトルを省いたとき(空の文字列も同じ)は、タイトルの欄を付けずに送ります。

フィールド(名前と値の組)を付けるときは、4つ目の引数に `Field` の配列で書きます。フィールドは省略することが可能です。

```php
use Citrus\Notification\Field;

Notification::fromConfigures($configures)->send('取り込みが終わりました', Level::SUCCESS, '[EventImport] 成功', [
    new Field('account', '0f3c...'),
    new Field('result', '3件を取り込みました', inline: false),
]);
```

横に並べるか(`inline`)を省いたときは、横に並べます。  
Discord では、フィールドが枠の `fields` に、渡した順に入ります。

- 名前か値が空の文字列のときは、`-` を入れて送ります
- 名前は 256 文字、値は 1024 文字までで、超えたぶんは切って終わりに `…（以下省略）` を付けます
- 文が空の文字列のときは、文の欄(`description`)を付けずに送ります
- フィールドの数(25 個まで)と、枠の文字数の合計(6000 文字まで)は、超えても減らさずに送ります。Discord が受け付けなければ、送れなかったものとして扱います
