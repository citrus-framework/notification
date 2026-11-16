<?php

declare(strict_types=1);

/**
 * @copyright   Copyright 2026, CitrusNotification. All Rights Reserved.
 * @author      take64 <take64@citrus.tk>
 * @license     http://www.citrus.tk/
 */

/**
 * テスト用の組み込みサーバーの入口
 * /hook/{ステータスコード} 受け取った本文と Content-Type を、環境変数 NOTIFICATION_RECORD のファイルに書き、
 *                          指定のステータスコードで返す(?sleep=N なら、N 秒眠ってから返す)
 */
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

if (preg_match('#^/hook/(\d{3})$#', $path, $matches) === 1)
{
    file_put_contents((string)getenv('NOTIFICATION_RECORD'), json_encode([
        'body' => file_get_contents('php://input'),
        'content_type' => $_SERVER['CONTENT_TYPE'] ?? '',
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    sleep((int)($_GET['sleep'] ?? 0));
    http_response_code((int)$matches[1]);
    return;
}

http_response_code(404);
