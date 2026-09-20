---
title: 開発者向け
nav_order: 7
---

# 開発者向け
{: .no_toc }

## 目次
{: .no_toc .text-delta }

1. TOC
{:toc}

---

## 技術スタック

- PHP 8.2 / Laravel 12
- MySQL 8.0（本番）／SQLite（ローカル開発・テスト）
- Blade + Tailwind CSS 4 + Vite、素の JavaScript（フロントエンドのフレームワークは使っていません）
- endroid/qr-code（QRコード）、maxmind-db/reader（国の判定）

## ローカル環境

必要なもの: PHP 8.2 以上（`curl` `fileinfo` `mbstring` `openssl` `pdo_sqlite` `pdo_mysql` `zip`）、Composer 2、Node.js 20.19 以上または 22.12 以上。

```bash
composer install
npm install
cp .env.example .env
php artisan key:generate
```

`.env.example` は本番向けの値です。ローカルでは次のように書き換えます。

```dotenv
APP_ENV=local
APP_DEBUG=true
APP_URL=http://localhost:8000

DB_CONNECTION=sqlite
# DB_HOST 〜 DB_PASSWORD はコメントアウト

SESSION_DOMAIN=null
SESSION_SECURE_COOKIE=false

# ブラウザは *.localhost を 127.0.0.1 に解決するため hosts の編集は不要
SHORTENER_MAIN_DOMAIN=localhost
SHORTENER_DASHBOARD_DOMAIN=dash.localhost
SHORTENER_API_DOMAIN=api.localhost
SHORTENER_REDIRECT_DOMAIN=redirect.localhost
SHORTENER_SHORT_URL_BASE=http://localhost:8000
```

テーブルの作成と予約語の投入を行い、インストール済みとして記録します。

```bash
php artisan app:install
```

```bash
npm run build
```

```bash
php artisan serve
```

| URL | 内容 |
|---|---|
| `http://localhost:8000/` | トップページ |
| `http://dash.localhost:8000/` | ダッシュボード |
| `http://localhost:8000/_preview` | トップページのプレビュー |
| `http://dash.localhost:8000/_preview` | ダッシュボードのプレビュー（一般ユーザー） |
| `http://dash.localhost:8000/_preview/admin` | ダッシュボードのプレビュー（管理者） |

`/_preview` は `APP_ENV=local` のときだけ有効で、データベースを使わずサンプルデータを表示します。

## テストとコードスタイル

```bash
php artisan test
```

```bash
vendor/bin/pint
```

テストは SQLite（メモリ）で動きます。外部サービスへの通信はすべて `Http::fake()` に置き換えているため、ネットワークには接続しません。

## 設定値の管理方針

| 種類 | 置き場所 | 例 |
|---|---|---|
| 環境ごとに異なる値 | `.env` | `APP_KEY`、データベース接続情報、サブドメイン |
| 業務ルールの初期値 | `config/shortener.php` | 月間発行上限、レート制限、有効期限の上限 |
| 管理画面で変更する値 | `app_settings` テーブル（機密値は暗号化） | サイト名、外部サービスのキー |
| 設置した人が書く文章 | `site_pages` テーブル（Markdown） | 利用規約、プライバシーポリシー |
| 予約語 | `reserved_words` テーブル | 管理画面で追加・削除 |

業務ルールの値は `.env` に書かず、`App\Support\ShortenerSettings` 経由で読み出します。

## ディレクトリ構成（主要部分）

```
app/
├── Console/Commands/       # app:install / app:update / app:health-check / app:periodic-tasks
├── Http/Controllers/
│   ├── Main/               # トップ・削除・固定ページ・お問い合わせ
│   ├── Auth/               # Discord ログイン・初回のログイン設定
│   ├── Dashboard/          # ダッシュボード
│   ├── Admin/              # 管理者機能
│   ├── Api/V1/             # API
│   ├── Redirect/           # 中間ページと転送確認
│   └── Install/            # セットアップ画面
├── Installer/              # Web インストーラ
├── Services/
│   ├── Auth/ Account/ ShortUrl/ Redirect/ Security/ Dashboard/
│   ├── GeoIp/              # 国判定データベースの自動取得
│   ├── Settings/ Site/     # 外部サービスのキー、固定ページのひな形
│   ├── Tasks/              # 定期処理（cron 不要）
│   └── Update/             # 自動アップデート
└── Support/                # 設定値・サイト名・URL 組み立て
resources/templates/legal/  # 利用規約・プライバシーポリシーのひな形（Markdown）
docs/                       # このドキュメント（GitHub Pages）
scripts/                    # 配布用 zip の作成
```

## リリース手順

1. `main` に Conventional Commits 形式（`feat:` `fix:` `docs:` など）でコミットして push します。
2. `vYY.MM.patch` 形式のタグを付けて push します（例: 2026年9月の最初のリリースなら `v26.9.0`）。

```bash
git tag v26.9.0
git push origin v26.9.0
```

タグを push すると GitHub Actions（`.github/workflows/release.yml`）が、テストの実行、配布用 zip の作成、GitHub Releases への添付までを行います。設置済みのサーバーは、翌日の定期処理でこの zip に更新されます。

Actions タブから「Release package」を手動実行すると、タグを付けずにテストと zip の作成だけを試せます。手元で作る場合は `bash scripts/build-release.sh v26.9.0` です。

## ドキュメント（このサイト）

`docs/` 以下の Markdown が GitHub Pages として公開されます。`main` の `docs/` を変更して push すると、GitHub Actions（`.github/workflows/docs.yml`）が自動で反映します。

ページを追加するときは、先頭に次のような front matter を書きます。

```yaml
---
title: ページ名
nav_order: 9
---
```

フォークした場合は、リポジトリの Settings → Pages → Build and deployment → Source で「GitHub Actions」を選ぶと、同じ手順で公開できます。
