---
title: インストール
nav_order: 2
---

# インストール
{: .no_toc }

## 目次
{: .no_toc .text-delta }

1. TOC
{:toc}

---

WordPress と同じように、ファイルを設置してブラウザで開くだけで始められます。サーバーで Composer・Node.js・SSH・cron を使う必要はありません。

## 1. 準備するもの

- MySQL 8.0 のデータベースとユーザー（文字コードは `utf8mb4`）
- 4つのサブドメイン（例: `example.com` `dash.example.com` `api.example.com` `redirect.example.com`）
- FTP などでファイルを置ける環境

## 2. ファイルを設置する

1. [Releases](https://github.com/choko1229/URL-Shortener/releases) から `url-shortener-vXX.X.X.zip` をダウンロードして展開します。
2. 展開した `url-shortener` フォルダの**中身**を、公開フォルダ（例: `public_html`）に丸ごとアップロードします。
3. 4つのサブドメインの公開フォルダを、すべて手順2のフォルダに向けます。SSL もここで設定します。

配布用の zip には、動作に必要なライブラリ（`vendor/`）とビルド済みの表示ファイルを同梱しています。

{: .note }
> 公開フォルダを `public/` に指定できるサーバーでは、そちらのほうが安全です。設置フォルダ直下に置いた場合は、同梱の `.htaccess` がすべてのリクエストを `public/` へ転送し、`.env` などへの直接アクセスを防ぎます（Apache の mod_rewrite と AllowOverride が必要です）。nginx など `.htaccess` が使えないサーバーでは、必ず公開フォルダを `public/` に指定してください。

## 3. セットアップ画面を開く

ブラウザでメインのドメイン（例: `https://example.com/`）を開くと、セットアップ画面が表示されます。

1. **動作環境**の確認結果を見ます。「NG」があると先に進めません（[困ったときは](troubleshooting.html)を参照）。
2. **データベースの接続情報**を入力します。入力するのはこれだけです。
3. 「セットアップを完了する」を押すと、テーブルの作成と初期データの登録まで自動で行います。

ドメインは、アクセスしているホスト名から自動で決まります（`example.com` で開けば `dash.` `api.` `redirect.` を付けたものになります）。変更したい場合だけ「ドメイン」を開いて編集します。

{: .warning }
> セットアップが完了するまで、この画面は誰でも操作できます。ファイルを設置したら、次の手順まで一気に進めてください。

## 4. Discord ログインを設定して管理者になる

完了画面のボタンから `https://dash.example.com/login` を開くと、Discord ログインの設定画面が表示されます。

1. [Discord Developer Portal](https://discord.com/developers/applications) で「New Application」からアプリケーションを作ります。
2. 「OAuth2」→「Redirects」に、設定画面に表示されている URL（例: `https://dash.example.com/login/callback`）を追加して保存します。
3. 同じ画面の Client ID と Client Secret を、設定画面に入力します。
4. 保存するとそのまま Discord のログイン画面へ進みます。**最初にログインした人が管理者**になります。

管理者が登録されると、この設定画面は使えなくなります（以後は「管理」の「外部サービス」タブで変更します）。

## 5. 仕上げ

- [カスタマイズ](customize.html): サイト名・運営者名、配色・書体・アイコン、利用規約・プライバシーポリシー。自分や身内だけで使うなら限定モード
- [外部サービス](services.html): 悪意URLチェック、スパム対策、管理者への通知（Discord Webhook）
- [運用](operations.html): 自動アップデートのしくみ（設定しなくても1日1回更新を確認します）

## 手作業が必要なこと

次の3つは自動化できないため、手作業になります。

- サーバーの管理画面での作業（データベースの作成、サブドメインの公開フォルダと SSL の設定）
- Discord Developer Portal での Redirect URI の登録
- 公開前の利用規約・プライバシーポリシーの確認

## 設置後に覚えておくこと

- 設置フォルダの `.env` には、データベースのパスワードと暗号鍵（`APP_KEY`）が入っています。バックアップを取り、他人に渡さないでください。`APP_KEY` を失うと、暗号化して保存した設定を復元できません。
- 設定をやり直したい場合は `storage/app/private/installed.json` を削除すると、セットアップ画面が再び開きます（テーブルや発行済みのデータは消えません）。
- SSH が使える場合は、`.env` を直接編集したうえで `php artisan app:install` でもセットアップできます。
