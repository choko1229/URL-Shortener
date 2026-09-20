<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * 短縮URLを Discord・X などに貼ったときのカード（OGP）の出し方。
 * パスワード保護つきのリンクは、転送先が漏れないよう常に Service として扱う。
 */
enum PreviewMode: string
{
    // 転送先のカードをそのまま見せる（クローラーは転送先へ通す）
    case Destination = 'destination';

    // 転送先を出さず、このサービスの名前だけのカードにする
    case Service = 'service';

    // タイトル・説明・画像を自分で指定する
    case Custom = 'custom';

    public function label(): string
    {
        return match ($this) {
            self::Destination => '転送先のカードを見せる',
            self::Service => 'カードを隠す',
            self::Custom => '内容を指定する',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Destination => '貼り付けたときに、転送先ページのタイトルや画像が表示されます。',
            self::Service => '転送先は表示されず、サービス名だけのカードになります。',
            self::Custom => '入力したタイトル・説明・画像でカードを表示します。',
        };
    }
}
