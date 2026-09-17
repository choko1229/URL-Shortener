# chok.ooo

個人利用・知人向けのシンプルなURL短縮サービスです。

- 要件定義: [requirements.md](requirements.md)
- デザインシステム: [design.md](design.md)

## サブドメイン構成

1つの Laravel アプリケーションで、サブドメインごとにルーティングします。

| サブドメイン | 役割 | 状態 |
|---|---|---|
| `chok.ooo` | トップページ（発行フォーム）／短縮URLへのアクセス受付 | 画面のみ実装 |
| `dash.chok.ooo` | ダッシュボード（発行履歴・統計・管理） | 画面のみ実装 |
| `api.chok.ooo` | 管理者専用 API | 未実装 |
| `redirect.chok.ooo` | リダイレクト確認・悪意URLチェック | 未実装 |

## 技術スタック

- PHP 8.2 / Laravel 12
- MySQL 8.0（本番）／SQLite（ローカル開発・テスト）
- Blade + Tailwind CSS 4 + Vite
- 素の JavaScript（外部ライブラリなし）

> 要件定義では Laravel 11 でしたが、11.x はサポート終了かつ未修正の脆弱性（CVE-2026-48019）があるため 12 を採用しています。

## ローカル開発環境のセットアップ

必要なもの: PHP 8.2 以上（`curl` `fileinfo` `mbstring` `openssl` `pdo_sqlite` `pdo_mysql` `zip` 拡張）、Composer 2、Node.js 20 以上

```bash
composer install
npm install
cp .env.example .env
php artisan key:generate
```

`.env.example` は本番向けの値になっているため、ローカルでは `.env` の次の項目を書き換えてください。

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

データベースを作成し、予約語の初期データを投入します。

```bash
php artisan migrate --seed
```

アセットをビルドして開発サーバーを起動します（開発中は `npm run build` の代わりに `npm run dev` も使えます）。

```bash
npm run build
```

```bash
php artisan serve
```

| URL | 内容 |
|---|---|
| http://localhost:8000/ | トップページ |
| http://dash.localhost:8000/ | ダッシュボード（ログイン必須のため、現状はトップページへ戻される） |
| http://localhost:8000/_preview | トップページのプレビュー（発行結果パネル付き） |
| http://dash.localhost:8000/_preview | ダッシュボードのプレビュー（一般ユーザー） |
| http://dash.localhost:8000/_preview/admin | ダッシュボードのプレビュー（管理者） |

`/_preview` 系は認証・保存処理の実装前に画面を確認するためのもので、`APP_ENV=local` のときだけ有効です（DB を使わずサンプルデータで表示）。

## テスト・コードスタイル

```bash
php artisan test
```

```bash
vendor/bin/pint
```

## 設定値の管理方針

| 種類 | 置き場所 | 例 |
|---|---|---|
| 環境ごとに異なる値・機密値 | `.env` | `APP_KEY`、DB 接続情報、サブドメイン |
| 業務ルールの初期値 | `config/shortener.php` | 月間発行上限、未ログイン時の最大有効期限 |
| 管理画面から変更する値 | `app_settings` テーブル（初期値を上書き） | 同上、GitHub トークン（暗号化して保存） |
| 予約語 | `reserved_words` テーブル | 初期データは `ReservedWordSeeder` |

業務ルールの値は `.env` に書かず、必ず `App\Support\ShortenerSettings` 経由で読み出してください。

## ディレクトリ構成（主要部分）

```
app/
├── Enums/                  # 状態・種別（UserRole, SlugType, LinkStatus, IconName など）
├── Http/
│   ├── Controllers/
│   │   ├── Main/           # chok.ooo
│   │   ├── Dashboard/      # dash.chok.ooo
│   │   └── Preview/        # local 専用プレビュー
│   ├── Middleware/         # セキュリティヘッダー
│   └── Requests/           # 入力検証
├── Models/
├── Policies/               # 削除権限など
├── Services/Dashboard/     # ダッシュボード表示データの組み立て（参照のみ）
├── Support/                # 設定値の読み出し、短縮URLの組み立て、ナビゲーション
├── View/Components/        # アイコンコンポーネント
└── ViewModels/             # Blade に渡す readonly なデータ
resources/
├── css/app.css             # design.md のデザイントークン（@theme）
├── js/app.js               # 開閉パネル・ダイアログ・コピー等
└── views/
    ├── components/         # レイアウト・ボタン等の共通部品
    ├── partials/           # 発行フォーム・発行結果・ページ送り等
    ├── main/               # トップページ
    ├── dashboard/          # ダッシュボード
    └── errors/             # エラーページ
routes/
├── web.php                 # サブドメインごとのルート
└── preview.php             # local 専用プレビュー
```

## 実装状況

実装済み（画面表示まわり）

- トップページ・ダッシュボードの Blade テンプレートとレスポンシブ対応
- デザイントークン（Tailwind CSS）と共通コンポーネント
- マイグレーション・モデル（users / short_urls / short_url_clicks / reserved_words / app_settings）
- 発行フォームの入力検証、削除の権限チェック、セキュリティヘッダー

未実装（バックエンド）

- Discord ログイン（OAuth2 / identify）、初代管理者の自動設定、管理者昇格
- 短縮URLの保存（コード生成・予約語・重複・月間上限・レート制限・reCAPTCHA）
- QRコード生成、削除・スラッグ編集、削除用トークンによる削除
- リダイレクトフロー（中間ページ・パスワード保護とロックアウト・Safe Browsing チェック・期限切れページ・アクセス記録）
- 統計詳細、管理画面（APIキー・予約語・ユーザー・設定）、退会
- API、初期セットアップウィザード、自動アップデート機構

## セキュリティに関する注意

- `.env`、DB ダンプ、バックアップ、証明書・鍵ファイルはコミットしないでください（`.gitignore` で除外しています）。
- 本番では `APP_ENV=production`・`APP_DEBUG=false` にしてください。`/_preview` 系ルートは読み込まれなくなります。
- 脆弱性を見つけた場合は Issue ではなく管理者へ直接連絡してください。
