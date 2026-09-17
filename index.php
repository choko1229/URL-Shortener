<?php

/*
| public_html 直下に設置したとき、.htaccess（mod_rewrite）が機能していない場合にだけ表示される。
| 正常に設定されていれば、すべてのリクエストは public/index.php で処理され、このファイルは使われない。
| アプリケーションは起動しない（Laravel を読み込まない）。
*/

http_response_code(503);
header('Content-Type: text/html; charset=UTF-8');
header('X-Robots-Tag: noindex');
?>
<!DOCTYPE html>
<html lang="ja">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>設置設定を確認してください | chok.ooo</title>
<style>
body{margin:0;padding:40px 16px;background:#F4FBFF;color:#0F2A3D;font-family:'Noto Sans JP',system-ui,sans-serif;line-height:1.7}
main{max-width:640px;margin:0 auto;background:#fff;border-radius:24px;padding:32px;box-shadow:0 12px 32px rgba(15,42,61,.08)}
h1{font-size:20px;margin:0 0 12px}p,li{font-size:14px}
</style>
</head>
<body>
<main>
<h1>設置設定を確認してください</h1>
<p>.htaccess による転送が機能していないため、chok.ooo を起動できません。次のどちらかを行ってください。</p>
<ul>
<li>サーバーで Apache の mod_rewrite と .htaccess（AllowOverride）を有効にする</li>
<li>公開フォルダ（ドキュメントルート）を、設置したフォルダ内の <code>public</code> に変更する</li>
</ul>
<p>この状態のままでは設定ファイルが外部から見える可能性があるため、早めに対応してください。</p>
</main>
</body>
</html>
