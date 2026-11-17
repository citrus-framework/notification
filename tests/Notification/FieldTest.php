<?php

declare(strict_types=1);

/**
 * @copyright   Copyright 2026, CitrusNotification. All Rights Reserved.
 * @author      take64 <take64@citrus.tk>
 * @license     http://www.citrus.tk/
 */

namespace Test\Notification;

use Citrus\Notification\Field;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * フィールドのテスト
 */
class FieldTest extends TestCase
{
    #[Test]
    public function construct_横に並べるかを省くとtrue()
    {
        $field = new Field('account', '0f3c');

        $this->assertSame('account', $field->name);
        $this->assertSame('0f3c', $field->value);
        $this->assertTrue($field->inline);
    }

    #[Test]
    public function construct_inlineにfalseを渡せる()
    {
        $field = new Field('account', '0f3c', inline: false);

        $this->assertFalse($field->inline);
    }
}
