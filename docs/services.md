---
title: 外部サービス
nav_order: 4
---

# 外部サービス
{: .no_toc }

## 目次
{: .no_toc .text-delta }

1. TOC
{:toc}

---

「管理」の「外部サービス」タブ（管理者のみ）で設定します。キー類は `APP_KEY` で暗号化してデータベースに保存し、画面には表示しません。

| 機能 | 必要なもの | 未設定のとき |
|---|---|---|
| Discord ログイン | Client ID / Client Secret | ログインできません |
| 悪意URLチェック | Google Safe Browsing の API キー | 転送時に「安全性を確認できませんでした」と表示し、利用者の判断に任せます |
| スパム対策 | reCAPTCHA のキー ID・プロジェクト ID・API キー | 未ログインの発行とお問い合わせを検証しません（レート制限と月間上限のみ） |
| 国の判定 | 不要（自動で取得します） | データを取得できるまで国を記録しません |
| 管理者への通知 | Discord の Webhook URL | 通知しません（ログには残ります） |
| 自動アップデート | 不要（更新元は設定済み） | — |

## Discord ログイン

1. [Discord Developer Portal](https://discord.com/developers/applications) でアプリケーションを作ります。
2. 「OAuth2」→「Redirects」に、画面に表示されている Redirect URI（例: `https://dash.example.com/login/callback`）を登録します。
3. Client ID と Client Secret を入力して保存します。

取得する情報は `identify` の範囲だけです（ユーザーID・ユーザー名・表示名・アイコン）。メールアドレスや参加サーバーの情報は取得しません。

## 悪意URLチェック（Google Safe Browsing）

1. Google Cloud のプロジェクトで「Safe Browsing API」を有効にします。
2. 「API とサービス」→「認証情報」で API キーを作り、使える API を Safe Browsing API に制限します。
3. 「外部サービス」の「API キー」に入力します。

転送のたびに確認し、結果は3日間キャッシュします。危険と判定されたら転送を中止し、確認できなかった場合は警告を表示して利用者が選べるようにします。確認するのは転送先の URL だけで、訪問者の IP アドレスは送りません。

## スパム対策（reCAPTCHA）

Google が推奨する方式（ブラウザでトークンを取得し、サーバーから「評価」を作成する方法）で判定します。Google Cloud のライブラリや認証ファイルをサーバーに置く必要はありません。

1. Google Cloud コンソールの「reCAPTCHA」で、ウェブサイト用のキー（**スコアベース**）を作り、ドメインにメインドメインを登録します。
2. 同じプロジェクトで「reCAPTCHA Enterprise API」を有効にします。
3. 「API とサービス」→「認証情報」で API キーを作り、使える API を reCAPTCHA Enterprise API に制限します。
4. 「外部サービス」に**キー ID（サイトキー）・プロジェクト ID・API キー**を入力します。

保存するときに、その組み合わせで評価を作成できるか Google に確認します。うまくいかない場合は Google からのエラー内容を表示します。

- 適用するのは、未ログインでの発行とお問い合わせの送信です。ログイン中は判定しません。
- スコアが 0.5 未満なら拒否します（`config/shortener.php` で変更できます）。
- Google 側に接続できないときは、発行を止めません（レート制限と月間上限で抑えます）。
- 評価は月 10,000 回まで無料です。

## 国の判定（設定不要）

クリック元の IP アドレスから国を判定します。判定に使うデータベースは自動で取得し、毎月更新します。

- データは [DB-IP](https://db-ip.com) の IP to Country Lite（CC BY 4.0）です。
- セットアップ後の最初の定期処理で `storage/app/private/geoip/` に取得します（約 8MB）。
- 「外部サービス」で、使用中のデータの版・取得日時の確認と、「今すぐ更新」ができます。
- 利用条件に従い、国の統計に「IP Geolocation by DB-IP」のリンクを表示します。
- 訪問者の IP アドレスを外部に送ることはありません。データベースをサーバー内で参照するだけです。

MaxMind の GeoLite2 Country（`GeoLite2-Country.mmdb`）を `storage/app/private/geoip/` に置くと、そちらを優先して使います（自動取得は止まります）。自動取得をやめたい場合は `.env` に `SHORTENER_GEOIP_AUTO_UPDATE=false` を設定します。

## 管理者への通知（Discord Webhook）

「管理」の「アップデート」タブで Webhook URL を設定します。次のときに通知します。

- 自動アップデートの成功・失敗
- お問い合わせが届いたとき

通知にメンションは含めません（`@everyone` などが書かれていても反応しません）。
