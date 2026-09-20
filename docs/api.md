---
title: API
nav_order: 6
---

# API
{: .no_toc }

## 目次
{: .no_toc .text-delta }

1. TOC
{:toc}

---

管理者専用の API です。自分が管理する他のプロジェクトから短縮URLを発行することを想定しています。第三者への公開は想定していません。

## APIキーの発行

「管理」の「APIキー」タブで発行します。キーは発行時に一度だけ表示され、以後は確認できません（データベースにはハッシュ値だけを保存します）。不要になったキーは同じ画面で無効にできます。

## 呼び出し方

`Authorization: Bearer` ヘッダーにキーを付けて、API 用のサブドメインへ送ります。レート制限と月間上限は適用しません。

```bash
curl -X POST https://api.example.com/v1/links \
  -H "Authorization: Bearer usk_xxxxxxxx" \
  -H "Content-Type: application/json" \
  -d '{"url": "https://example.org/very/long/path"}'
```

## エンドポイント

| メソッド | パス | 内容 |
|---|---|---|
| `POST` | `/v1/links` | 発行 |
| `GET` | `/v1/links` | 自分が発行した短縮URLの一覧（50件ずつ、`?page=2`） |
| `GET` | `/v1/links/{コード}` | 詳細（クリック数を含む） |
| `DELETE` | `/v1/links/{コード}` | 削除（論理削除。コードは再利用されません） |

QRコードは `https://example.com/{コード}/qr.svg`（または `.png`）でも取得できます（認証不要）。

### 発行時に指定できる項目

| 項目 | 必須 | 内容 |
|---|---|---|
| `url` | 必須 | 短縮する URL（`http` / `https`） |
| `slug` | | カスタムスラッグ（3〜20文字、英数字と `-` `_`） |
| `expires_at` | | 有効期限（ISO 8601 形式。タイムゾーンを省略すると日本時間として扱います） |
| `password` | | パスワード保護（4〜72文字） |

## レスポンス例（201）

```json
{
  "data": {
    "code": "aB3xQ9k",
    "short_url": "https://example.com/aB3xQ9k",
    "original_url": "https://example.org/very/long/path",
    "custom_slug": false,
    "expires_at": null,
    "expired": false,
    "password_protected": false,
    "click_count": 0,
    "created_at": "2026-09-20T12:00:00+00:00",
    "qr_code": "data:image/svg+xml;base64,..."
  }
}
```

## エラー

`{"message": "...", "errors": {"項目": ["..."]}}` 形式の JSON を返します。

| ステータス | 意味 |
|---|---|
| 401 | APIキーが無効、または指定されていない |
| 403 | 管理者ではないユーザーのキー |
| 404 | 見つからない（削除済みを含む） |
| 422 | 入力エラー（`errors` に項目ごとの理由） |
