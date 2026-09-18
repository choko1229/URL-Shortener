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

## サーバーへのインストール（WordPress と同じ手順）

サーバー側で Composer や Node.js を使う必要はありません。

1. [Releases](https://github.com/choko1229/URL-Shortener/releases) から `chok-ooo-vX.X.X.zip` をダウンロードして展開する（まだリリースが無い場合は、下記「[開発フロー・リリース手順](#開発フローリリース手順)」でタグを作成すると自動で作られます）
2. サーバーの管理画面で MySQL 8.0 のデータベースとユーザーを作成する（文字コード utf8mb4）
3. 展開した `chok-ooo` フォルダの**中身**を、FTP で公開フォルダ（例: `public_html`）に丸ごとアップロードする
4. `chok.ooo` / `dash.chok.ooo` / `api.chok.ooo` / `redirect.chok.ooo` の公開フォルダを、すべて手順3のフォルダに向ける
5. ブラウザで `https://chok.ooo/` を開き、表示されるセットアップ画面に沿って進める
   1. 動作環境の確認（PHP・拡張モジュール・書き込み権限・設定ファイルが外から見えないこと）
   2. データベースの接続情報を入力
   3. ドメインと Discord の Client ID / Secret（任意）を入力 → テーブル作成・初期データ登録まで自動で完了

> **注意**: WordPress と同様、セットアップが完了するまでは誰でもセットアップ画面を操作できます。アップロードしたら、すぐに最後まで進めてください。

### 設置に関する補足

- 公開フォルダの直下に置いた場合、直下の `.htaccess` がすべてのリクエストを `public/` へ転送し、`.env` などへの直接アクセスを防ぎます。Apache の mod_rewrite と `.htaccess`（AllowOverride）が必要です。
- 管理画面で公開フォルダを `public/` に指定できる場合は、そちらのほうがより安全です（同じ zip のままで動きます）。
- nginx など `.htaccess` を使えないサーバーでは、必ず公開フォルダを `public/` に指定してください。
- セットアップ時に `.env`（DB パスワードと暗号鍵 APP_KEY を含む）が自動生成されます。バックアップを取り、他人に渡さないでください。
- 設定をやり直す場合は `storage/app/private/installed.json` を削除すると、セットアップ画面が再び開きます。
- SSH が使える場合は、`.env` を直接編集したうえで `php artisan app:install` でもセットアップできます。

### 配布用 zip の作り方

`vYY.MM.patch` 形式のタグ（例: `v26.9.0`）を push すると、GitHub Actions がテストを実行し、`vendor/` とビルド済みアセットを同梱した zip を Releases に添付します（`.github/workflows/release.yml`）。

手元で作る場合は次のコマンドを実行します（`build/release/` に出力。Composer と Node.js が必要）。

```bash
bash scripts/build-release.sh v26.9.0
```

## ローカル開発環境のセットアップ

必要なもの: PHP 8.2 以上（`curl` `fileinfo` `mbstring` `openssl` `pdo_sqlite` `pdo_mysql` `zip` 拡張）、Composer 2、Node.js 20.19 以上または 22.12 以上（Vite 7 の要件）

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

データベースの作成・予約語の初期データ投入を行い、インストール済みとして記録します（記録が無いとセットアップ画面へ転送されます）。

```bash
php artisan app:install
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

## 開発フロー・リリース手順

1. `feature/〇〇` ブランチで作業し、Conventional Commits 形式（`feat:` `fix:` `docs:` `test:` `chore:` `ci:` など）でコミットする
2. GitHub で `main` への Pull Request を作成してマージする
3. `main` に `vYY.MM.patch` 形式のタグ（例: 2026年9月の最初のリリースなら `v26.9.0`）を付けて push する

```bash
git switch main
git pull
git tag v26.9.0
git push origin v26.9.0
```

タグを push すると GitHub Actions（`.github/workflows/release.yml`）が次を自動で行います。

- テストの実行（失敗した場合は zip を作らない）
- `vendor/`（本番用）とビルド済みアセットを同梱した配布用 zip の作成
- GitHub Releases の作成と zip の添付

Actions タブから「Release package」を手動実行すると、タグを付けずにテストと zip の作成だけを試せます（zip は実行結果の Artifacts からダウンロード）。

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
│   ├── Middleware/         # セキュリティヘッダー、未インストール時のセットアップ画面への誘導
│   └── Requests/           # 入力検証
├── Installer/              # Web インストーラ（.env 生成・動作環境確認・DB 接続確認・セットアップ処理）
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
├── web.php                 # サブドメインごとのルート、セットアップ画面（/install）
└── preview.php             # local 専用プレビュー
scripts/                    # 配布用 zip の作成
.htaccess / index.php       # 公開フォルダ直下に設置した場合の転送設定・案内ページ
```

## 実装状況

実装済み（画面表示まわり）

- トップページ・ダッシュボードの Blade テンプレートとレスポンシブ対応
- デザイントークン（Tailwind CSS）と共通コンポーネント
- マイグレーション・モデル（users / short_urls / short_url_clicks / reserved_words / app_settings）
- 発行フォームの入力検証、削除の権限チェック、セキュリティヘッダー
- Web インストーラ（ファイル設置 → ブラウザでセットアップ）と配布用 zip の自動作成

未実装（バックエンド）

- Discord ログイン（OAuth2 / identify）、初代管理者の自動設定、管理者昇格
- 短縮URLの保存（コード生成・予約語・重複・月間上限・レート制限・reCAPTCHA）
- QRコード生成、削除・スラッグ編集、削除用トークンによる削除
- リダイレクトフロー（中間ページ・パスワード保護とロックアウト・Safe Browsing チェック・期限切れページ・アクセス記録）
- 統計詳細、管理画面（APIキー・予約語・ユーザー・設定）、退会
- API、自動アップデート機構

## トラブルシューティング

### セットアップ時

| 表示される内容 | 原因と対処 |
|---|---|
| 「vendor フォルダが見つかりません」 | リポジトリのソースを設置している。Releases の配布用 zip を設置する |
| 「設置設定を確認してください」 | `.htaccess` の転送が効いていない。mod_rewrite と AllowOverride を有効にするか、公開フォルダを `public/` にする |
| 「セットアップを開始できません」（フォルダに書き込めません） | `storage/` 配下と `bootstrap/cache/` のパーミッションを 755（環境によっては 775）にする |
| 「設定ファイルが外部から見えないこと」が **NG** | `.env` などが外から読める状態。上と同じく `.htaccess` の設定か公開フォルダを見直す（解決するまで先へ進めない） |
| 同じ項目が **要確認** | サーバーが自分自身へアクセスできない環境。表示されたリンクを開き、ファイルの中身が表示されないことを確認してチェックを入れる |
| 「ページの有効期限が切れました」（419） | 最初にアクセスしたときと違うスキーム（http / https）で開いている。最初と同じ URL で開き直す |

### Windows の開発環境

- **PHP / Composer で `certificate verify failed`**: ウイルス対策ソフトの HTTPS スキャンが独自の証明書で通信を中継していることがあります。Windows が信頼しているルート証明書を CA バンドルに追加し、`php.ini` の `openssl.cafile` と `curl.cainfo` に指定してください（証明書の検証自体は無効にしないこと）。
- **Composer で `Could not delete ... antivirus`**: ウイルス対策ソフトが展開中のファイルをロックしています。除外設定を行うか、配布用 zip は GitHub Actions で作成してください。

## 既知の課題

- design.md のカラートークンには WCAG AA のコントラスト比を満たさない組み合わせがある（プライマリボタンの白文字 2.06:1、注意色 2.73:1 など）
- CSP（Content-Security-Policy）ヘッダーが未設定。POST のレート制限は発行処理とあわせて実装予定
- 公開フォルダ直下に設置する場合の `.htaccess` は、実サーバー（kagoya）での動作が未検証
- 自動アップデート（requirements.md 7章）はサーバー上での git / Composer 実行を前提としており、配布用 zip 方式との整合を検討する必要がある
- requirements.md 7-2 の「`.env` には APP_KEY のみ」と異なり、DB 接続情報は `.env` に保存している（DB 自体には保存できないため）

## セキュリティに関する注意

- `.env`、DB ダンプ、バックアップ、証明書・鍵ファイルはコミットしないでください（`.gitignore` で除外しています）。
- 本番では `APP_ENV=production`・`APP_DEBUG=false` にしてください（セットアップ画面が自動生成する `.env` はこの設定です）。`/_preview` 系ルートは読み込まれなくなります。
- セットアップ画面は完了するまで誰でも操作できます。設置したらすぐに完了させてください。完了後に `storage/app/private/installed.json` を消すとセットアップ画面が再び開くため、FTP アカウントの管理にも注意してください。
- 脆弱性を見つけた場合は Issue ではなく管理者へ直接連絡してください。
