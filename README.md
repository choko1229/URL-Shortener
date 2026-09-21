# URL-Shortener

自分のサーバーに置いて使える、シンプルなURL短縮サービスです（オープンソース）。**設置した人が自分のサービスとして使えるように作ってあります**。サイト名・運営者名・利用規約・プライバシーポリシーは、設置後に管理画面から変更できます（コードの書き換えは不要です）。

- **ドキュメント（設置・運用の手引き）: <https://choko1229.github.io/URL-Shortener/>**
- ライセンス: [MIT](LICENSE)
- 要件定義: [requirements.md](requirements.md)
- デザインシステム: [design.md](design.md)

## サブドメイン構成

1つの Laravel アプリケーションで、サブドメインごとにルーティングします（requirements.md 1-1）。

| サブドメイン | 役割 |
|---|---|
| `example.com` | トップページ（発行フォーム）／短縮URLへのアクセス受付（`example.com/abc1234`）／削除用トークンによる削除（`/delete`）／利用規約・プライバシーポリシー・お問い合わせ |
| `dash.example.com` | Discord ログイン、ダッシュボード（発行・履歴・統計・編集・削除）、設定・退会、管理者機能 |
| `api.example.com` | 管理者専用 API（`/v1/links`） |
| `redirect.example.com` | リダイレクト確認・悪意URLチェック |

## 主な機能

| 機能 | 内容 |
|---|---|
| 発行 | ランダムコード（7桁）。ログインするとカスタムスラッグ（3〜20文字。管理者は1文字から）・無期限も使える。パスワード保護・有効期限（未ログインは最大30日）・QRコード |
| 利用制限 | ログイン: 60件/月・5回/分、未ログイン: 5件/月・3分に1回（IP 単位）。未ログインの発行は reCAPTCHA（スコアベース）で確認 |
| リダイレクト | 中間ページ → `redirect.example.com` で移動先を表示し Safe Browsing で確認 → 自動で移動（下記） |
| 統計 | クリック数・日別推移・リファラ・国・デバイス。本人のリンクはダッシュボードで、未ログイン発行分は管理者のみ閲覧 |
| QRコード | 発行時・ダッシュボードで表示。SVG / PNG を選んでダウンロードできる（`/{コード}/qr.svg` `/{コード}/qr.png`） |
| 共有時のカード | Discord や X に貼ったときの表示をリンクごとに選べる（転送先のカードを見せる／隠す／内容を指定する）。パスワード保護つきは常に隠す |
| 編集・削除 | 本人が編集できるのはカスタムスラッグと共有時のカード（管理者は元URL・有効期限・発行者も変更できる）。削除・編集で使われなくなったコードは欠番として再利用しない（論理削除） |
| ログイン | Discord（identify スコープのみ）。セットアップ後に最初にログインしたユーザーが管理者になる |
| 管理者 | 全URLの管理・削除・編集（元URL・有効期限・発行者）、CSV でのまとめて発行、ユーザーの管理者昇格、予約語の追加・削除、APIキーの発行、お問い合わせの確認、アップデート管理、予約語でもカスタムスラッグにできる。これらはヘッダーの「管理」にまとまっており、1つの画面のタブで切り替える |
| お問い合わせ | フォームの内容をデータベースに保存し、管理画面で読む。Discord Webhook を設定していれば届いたときに通知（メール送信の設定は不要） |
| 退会 | Discord の情報を削除して論理削除。発行した短縮URLを一緒に削除するかは選べる |
| カスタマイズ | サイト名・キャッチコピー・運営者名を管理画面で変更。利用規約・プライバシーポリシーは Markdown で編集でき、実装に沿ったテンプレートを読み込める |
| 自動アップデート | 1日1回 GitHub Releases を確認し、バックアップ（3世代）→ 更新 → マイグレーション → ヘルスチェック。失敗したら自動で戻して Discord に通知 |

### リダイレクトの流れ

1. `example.com/{コード}` を開くと中間ページを返す（期限切れは専用ページ、削除済み・存在しないコードは 404）
   - パスワード保護ありの場合はここでパスワードを入力（同じ IP から 5 回間違えると 15 分ロック）
2. 中間ページの JavaScript が `redirect.example.com/go` へ暗号化したチケットを自動 POST する（アクセス数はここで記録）
3. `redirect.example.com` が移動先を表示し、Google Safe Browsing で安全性を確認する（結果は 3 日間キャッシュ）
   - 安全 → すぐに移動 / 危険 → 移動を中止 / 確認できない → 警告を表示し「それでも開く」で利用者に任せる
   - 限定モードで、管理者・許可したユーザーが発行したものは確認せず、`redirect.example.com/go` からそのまま移動する

短縮コードは、ランダムコードに限り大文字小文字を無視して照合します（`example.com/ABC1234` でも `aBc1234` に届く）。カスタムスラッグは完全一致です。

## 技術スタック

- PHP 8.2 / Laravel 12
- MySQL 8.0（本番）／SQLite（ローカル開発・テスト）
- Blade + Tailwind CSS 4 + Vite、素の JavaScript（外部ライブラリなし）
- endroid/qr-code（QRコード）、maxmind-db/reader（国の判定。データは DB-IP の IP to Country Lite を自動取得）

> 11.x はサポート終了かつ未修正の脆弱性（CVE-2026-48019）があるため 12 を採用しています（requirements.md も 12.x に更新済み）。

## サーバーへのインストール（WordPress と同じ手順）

サーバー側で Composer・Node.js・SSH・cron を使う必要はありません。**ファイルを置いて、ブラウザで開いて、DB の接続情報を入力するだけ**です。

1. [Releases](https://github.com/choko1229/URL-Shortener/releases) から `url-shortener-vX.X.X.zip` をダウンロードして展開する（まだリリースが無い場合は、下記「[リリース手順](#リリース手順)」でタグを作成すると自動で作られます）
2. サーバーの管理画面で MySQL 8.0 のデータベースとユーザーを作成する（文字コード utf8mb4）
3. 展開した `url-shortener` フォルダの**中身**を、FTP で公開フォルダ（例: `public_html`）に丸ごとアップロードする
4. `example.com` / `dash.example.com` / `api.example.com` / `redirect.example.com` の公開フォルダを、すべて手順3のフォルダに向ける（SSL もここで設定）
5. ブラウザで `https://example.com/` を開くとセットアップ画面が表示される。動作環境の確認結果を見て、**データベースの接続情報を入力するだけ**で完了（テーブル作成・初期データ登録まで自動。ドメインはアクセス中のホスト名から自動で決まり、必要なときだけ変更できる）
6. 完了画面のボタンから `https://dash.example.com/login` を開くと、Discord ログインの設定画面が表示される。画面に出る Redirect URI を [Discord Developer Portal](https://discord.com/developers/applications) に登録し、Client ID / Secret を入力すると、そのまま Discord ログインへ進む（**最初にログインした人が管理者**になる）
7. 必要に応じて、「管理」の「外部サービス」タブで悪意URLチェック・reCAPTCHA を、「アップデート」で GitHub のトークンと Discord Webhook を設定する

> **注意**: WordPress と同様、セットアップが完了するまでは誰でもセットアップ画面を操作できます。Discord ログインの設定画面も、管理者が登録されるまでは誰でも開けます。アップロードしたら、すぐに最後まで（管理者としてログインするまで）進めてください。

サーバーの管理画面でしかできない作業（サブドメインの公開フォルダ・SSL の設定、データベースの作成）と、Discord 側での Redirect URI の登録だけは、自動化できないため手作業になります。セットアップ画面では、サブドメインがこのフォルダを向いているかも確認します（向いていなくても「注意」として進めます）。

### 定期処理

次の処理は、**サイトへのアクセスをきっかけに自動で実行**されます（cron は使いません）。

- 自動アップデートの確認: 1 日 1 回（日本時間 4:00 を過ぎてから最初のアクセス時）
- 国判定のデータベースの取得・更新: 無ければすぐに（失敗したら 1 時間ごとに再試行）、以後は毎月新しい版が出たら

実行のしくみは次のとおりです。

- PHP-FPM / LiteSpeed の環境では、訪問者へ応答を返し終えた後に同じプロセスで実行するため、訪問者を待たせません
- それ以外の環境では、自分自身へ合言葉（APP_KEY から生成）付きのリクエスト（`POST /_cron`）を送り、そちらで実行します
- 実行の確認は数分に 1 回だけ行うため、通常のアクセスに負荷はかかりません。前回の実行時刻は「管理」の「アップデート」タブに表示されます
- 更新時の `migrate` などは別プロセスの PHP（CLI）で実行します。PHP（CLI）を起動できないサーバーでは、同じプロセスの中で実行します

アクセスが少ないサイトで時刻どおりに実行したい場合は、cron を登録することもできます（登録されていれば cron を優先し、アクセスでの実行は止まります）。

```
* * * * * cd /path/to/url-shortener && php artisan schedule:run >> /dev/null 2>&1
```

cron だけで動かしたい場合は `.env` に `SHORTENER_WEB_CRON=false` を設定します。

### 設置に関する補足

- 公開フォルダの直下に置いた場合、直下の `.htaccess` がすべてのリクエストを `public/` へ転送し、`.env` などへの直接アクセスを防ぎます。Apache の mod_rewrite と `.htaccess`（AllowOverride）が必要です。
- 管理画面で公開フォルダを `public/` に指定できる場合は、そちらのほうがより安全です（同じ zip のままで動きます）。
- nginx など `.htaccess` を使えないサーバーでは、必ず公開フォルダを `public/` に指定してください。
- セットアップ時に `.env`（DB パスワードと暗号鍵 APP_KEY を含む）が自動生成されます。バックアップを取り、他人に渡さないでください。APP_KEY を失うと、暗号化して保存したキー類を復元できません。
- 設定をやり直す場合は `storage/app/private/installed.json` を削除すると、セットアップ画面が再び開きます（テーブルや発行済みのデータは消えません）。
- SSH が使える場合は、`.env` を直接編集したうえで `php artisan app:install` でもセットアップできます。

### 設置後のカスタマイズ

「管理」の「サイト設定」タブ（管理者のみ）で変更します。

| 項目 | 内容 |
|---|---|
| サイト名 | ヘッダーのロゴ・ページタイトル・通知・エラーメッセージに使われます。未設定のときはメインドメイン（例: `example.com`）を表示します |
| キャッチコピー | トップページのタイトル「サイト名 - キャッチコピー」に使います |
| 運営者名 | 利用規約・プライバシーポリシーに表示します |
| メインカラー | プリセット6色から選ぶか、カラーコードで指定します。濃淡・枠線・罫線はこの1色から自動で作り、文字色はコントラスト比 4.5:1 を満たすよう自動調整します |
| ダークモード | 「ライトのみ / 端末の設定に合わせる / ダークを既定にする」から選びます。ライトのみ以外では、ヘッダーの切り替えボタンで訪問者も選べます |
| 書体 | 丸ゴシック・ゴシック・角ゴシック・やわらかい丸ゴシック・明朝（Google Fonts） |
| サービスアイコン | 内蔵アイコン6種類から選ぶか、画像（PNG / JPG / WebP・512KB まで）をアップロードします。ファビコンにも同じものを使います |
| 公開範囲 | 「限定モード」にすると、管理者と「ユーザー」タブで許可した人だけが発行・ダッシュボードを使えます。それ以外の人がトップページやダッシュボードを開くと、「このドメインについて」（Markdown で編集）を表示するか、指定した別の URL へ移動します。発行済みの短縮URLは誰でも開けます |
| ページのURL | 利用規約・プライバシーポリシー・お問い合わせ・削除ページの URL を変えられます（既定は `/terms` `/privacy` `/contact` `/delete`）。変えると、元の語を管理者が短縮URLとして使えます |
| 利用規約 / プライバシーポリシー | **Markdown** で本文を書きます。「テンプレートを入力」でひな形を編集欄に読み込み、修正してから保存します。保存するとフッターにリンクが出ます（未作成のページは 404 で、フッターにも出ません） |

- テンプレートは、このソフトウェアが実際に行っている処理（保存する情報、外部への送信、削除の扱いなど）に沿った下書きです。そのまま使えることを保証するものではないため、内容を確認し、運営の実態に合わせて修正してから公開してください。
- 本文に書いた HTML は表示時に取り除きます（見出し・箇条書き・リンク・表などは Markdown で書けます）。
- 「お問い合わせ」ページは常に利用できます。届いた内容は「管理」の「お問い合わせ」タブで読め、Discord Webhook を設定していれば通知します。公開する Discord の連絡先もそこで設定できます。
- `.env` で既定値を決めることもできます（`SHORTENER_SITE_NAME` `SHORTENER_SITE_TAGLINE` `SHORTENER_OPERATOR_NAME`）。管理画面の設定が優先されます。

### 外部サービスの設定

| 機能 | 必要なもの | 設定場所 | 未設定の場合 |
|---|---|---|---|
| ログイン | Discord の Client ID / Client Secret | 初回: `dash.example.com/login` を開くと表示される設定画面。以後: 「管理」の「外部サービス」タブ | ログインできない |
| 悪意URLチェック | Google Safe Browsing API キー（Google Cloud で Safe Browsing API を有効化） | 「管理」の「外部サービス」タブ | 転送時に「安全性を確認できませんでした」と表示し、利用者が判断して移動 |
| スパム対策（未ログインの発行） | Google Cloud の reCAPTCHA キー（ウェブサイト用・スコアベース）の キー ID、プロジェクト ID、API キー（reCAPTCHA Enterprise API を有効にし、API キーの使える API をそれに制限） | 「管理」の「外部サービス」タブ（保存時に Google で使えるか確認） | 検証しない（レート制限と月間上限のみ） |
| アクセス元の国 | 不要（DB-IP の無料データベースを自動で取得し、毎月更新） | 設定不要。「外部サービス」で状態の確認と今すぐ更新ができる | 取得できるまで国を記録しない（他の項目は記録する） |
| 自動アップデート | 不要（更新元 `choko1229/URL-Shortener` は設定済み。非公開リポジトリから更新する場合のみトークン） | 「管理」の「アップデート」タブ | 既定で有効。無効にもできる |
| 管理者への通知 | Discord Webhook URL | 「管理」の「アップデート」タブ | 通知しない（ログには残る） |

- キー類は APP_KEY で暗号化してデータベースに保存します（reCAPTCHA のキー ID とプロジェクト ID は公開してよい値のため平文）。
- reCAPTCHA は Google が推奨する方式（ブラウザで `enterprise.js` のトークンを取得し、サーバーから評価を作成）で判定します。Google Cloud のライブラリや認証ファイルは不要で、API キーだけで動きます。評価は月 10,000 回まで無料です。
- 国の判定には [DB-IP](https://db-ip.com) の IP to Country Lite（CC BY 4.0）を使います。セットアップ後の最初の定期処理で `storage/app/private/geoip/` に取得し（約 8MB）、以後は新しい月の版が出たら自動で差し替えます。利用条件に従い、国の統計には「IP Geolocation by DB-IP」のリンクを表示します。
- MaxMind GeoLite2 Country（`GeoLite2-Country.mmdb`）を `storage/app/private/geoip/` に置くと、そちらを優先して使います（自動取得は止まります）。自動取得を止めたい場合は `.env` に `SHORTENER_GEOIP_AUTO_UPDATE=false` を設定します。
- 訪問者の IP は外部に送らず、保存もしません（データベースはサーバー内で参照します）。

## 自動アップデート（requirements.md 7 章）

1日1回（日本時間 4:00 以降、上記「[定期処理](#定期処理)」の仕組みで）、次の順に処理します。

1. GitHub Releases（既定の更新元は `choko1229/URL-Shortener`）の最新リリース（`vYY.MM.patch` 形式のタグ）を確認する。現在より新しくなければ何もしない
2. バックアップを取る（`storage/app/private/backups/`、直近 3 世代を保持）
   - DB ダンプ: `mysqldump` を使い、使えないサーバーでは PHP で同等のダンプを作る
   - コード一式: `app` `bootstrap` `config` `database` `public` `resources` `routes` `vendor` と直下のファイル
3. 新しいリリースを配置する
   - **Git で設置した環境**: `git fetch` → 新しいタグへ `git checkout` → `composer install --no-dev`
   - **配布用 zip で設置した環境**: Releases に添付された zip（vendor 同梱）をダウンロードしてコードを入れ替える
4. `php artisan migrate --force` を実行する
5. ヘルスチェック（`php artisan app:health-check`）: DB 接続、仮の短縮URLを発行して検索できるか（発行したデータは残さない）、ビルド済みアセットの有無
6. 成功したら Discord に「成功」を通知する。失敗したら、コード（Git なら更新前のコミットへ checkout）と DB をバックアップから戻し、「失敗（ロールバック済み）」を通知する

| コマンド | 内容 |
|---|---|
| `php artisan app:update --check` | 最新リリースの確認のみ |
| `php artisan app:update --manual` | 自動アップデートが無効でも今すぐ実行 |
| `php artisan app:health-check` | ヘルスチェックのみ |

PHP（CLI）はサーバー上の一般的な場所から自動で探します。実行ファイルの場所がサーバーで異なる場合は `.env` の `SHORTENER_PHP_BINARY` `SHORTENER_COMPOSER_BINARY` `SHORTENER_GIT_BINARY` `SHORTENER_MYSQLDUMP_BINARY` で指定できます。

## API（requirements.md 5 章）

管理者専用です。「管理」の「APIキー」タブで発行したキーを `Authorization: Bearer` ヘッダーに付けて呼び出します。レート制限・月間上限はありません。

| メソッド | パス | 内容 |
|---|---|---|
| `POST` | `https://api.example.com/v1/links` | 発行。`url`（必須）、`slug`、`expires_at`（ISO 8601。タイムゾーン省略時は日本時間）、`password` |
| `GET` | `https://api.example.com/v1/links` | 自分が発行した短縮URLの一覧（50件ずつ、`?page=2`） |
| `GET` | `https://api.example.com/v1/links/{コード}` | 詳細（クリック数を含む） |
| `DELETE` | `https://api.example.com/v1/links/{コード}` | 削除（論理削除） |

```bash
curl -X POST https://api.example.com/v1/links -H "Authorization: Bearer usk_xxxxxxxx" -H "Content-Type: application/json" -d "{\"url\": \"https://booth.pm/ja/items/1\"}"
```

レスポンス例（201）:

```json
{
  "data": {
    "code": "aB3xQ9k",
    "short_url": "https://example.com/aB3xQ9k",
    "original_url": "https://booth.pm/ja/items/1",
    "custom_slug": false,
    "expires_at": null,
    "expired": false,
    "password_protected": false,
    "click_count": 0,
    "created_at": "2026-09-18T12:00:00+00:00",
    "qr_code": "data:image/svg+xml;base64,..."
  }
}
```

エラーは `{"message": "...", "errors": {"項目": ["..."]}}` 形式の JSON（401: キーが無効、403: 管理者ではない、404: 見つからない、422: 入力エラー）。

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
| http://dash.localhost:8000/ | ダッシュボード（Discord ログインが必要。Redirects に `http://dash.localhost:8000/login/callback` を登録） |
| http://localhost:8000/_preview | トップページのプレビュー（発行結果パネル付き） |
| http://dash.localhost:8000/_preview | ダッシュボードのプレビュー（一般ユーザー） |
| http://dash.localhost:8000/_preview/admin | ダッシュボードのプレビュー（管理者） |

`/_preview` 系は `APP_ENV=local` のときだけ有効です（DB を使わずサンプルデータで表示）。

## テスト・コードスタイル

```bash
php artisan test
```

```bash
vendor/bin/pint
```

## ドキュメント（GitHub Pages）

`docs/` 以下の Markdown を [Just the Docs](https://just-the-docs.com/) テーマでビルドし、GitHub Pages に公開します。`main` の `docs/` を変更して push すると、GitHub Actions（`.github/workflows/docs.yml`）が自動で反映します。

| ファイル | 内容 |
|---|---|
| `docs/index.md` | 概要 |
| `docs/install.md` | インストール |
| `docs/customize.md` | カスタマイズ（サイト名・法的ページ・お問い合わせ） |
| `docs/services.md` | 外部サービス |
| `docs/operations.md` | 運用（定期処理・自動アップデート・バックアップ） |
| `docs/api.md` | API |
| `docs/development.md` | 開発者向け |
| `docs/troubleshooting.md` | 困ったときは |

初回だけ、リポジトリの Settings → Pages → Build and deployment → Source を「**GitHub Actions**」に変更してください（非公開リポジトリのままで使う場合は GitHub Pro 以上のプランが必要です）。ページを追加するときは、先頭に `title` と `nav_order` の front matter を書きます。

## リリース手順

1. `main` に Conventional Commits 形式（`feat:` `fix:` `docs:` `test:` `chore:` `ci:` など）でコミットし、push する
2. `main` に `vYY.MM.patch` 形式のタグ（例: 2026年9月の最初のリリースなら `v26.9.0`）を付けて push する

```bash
git tag v26.9.0
git push origin v26.9.0
```

タグを push すると GitHub Actions（`.github/workflows/release.yml`）が次を自動で行います。

- テストの実行（失敗した場合は zip を作らない）
- `vendor/`（本番用）とビルド済みアセット・`VERSION` ファイルを同梱した配布用 zip の作成
- GitHub Releases の作成と zip の添付（設置済みのサーバーは翌日の自動アップデートでこの zip に更新される）

Actions タブから「Release package」を手動実行すると、タグを付けずにテストと zip の作成だけを試せます。手元で作る場合は `bash scripts/build-release.sh v26.9.0`（`build/release/` に出力。Composer と Node.js が必要）。

## 設定値の管理方針

| 種類 | 置き場所 | 例 |
|---|---|---|
| 環境ごとに異なる値 | `.env` | `APP_KEY`、DB 接続情報、サブドメイン、実行ファイルの場所 |
| 業務ルールの初期値 | `config/shortener.php` | 月間発行上限、レート制限、未ログイン時の最大有効期限、ロックアウト |
| 管理画面で変更する値 | `app_settings` テーブル（初期値を上書き。機密値は暗号化） | サイト名・運営者名、Discord・Safe Browsing・reCAPTCHA のキー、GitHub トークン、Webhook URL |
| 設置した人が書く文章 | `site_pages` テーブル（Markdown） | 利用規約、プライバシーポリシー |
| 予約語 | `reserved_words` テーブル（ダッシュボードで追加・削除） | 初期データは `ReservedWordSeeder` |

業務ルールの値は `.env` に書かず、必ず `App\Support\ShortenerSettings` 経由で読み出してください。

## ディレクトリ構成（主要部分）

```
app/
├── Console/Commands/       # app:install / app:update / app:health-check / app:periodic-tasks
├── Enums/                  # 状態・種別（UserRole, SlugType, LinkStatus, IconName など）
├── Http/
│   ├── Controllers/
│   │   ├── Main/           # メインドメイン（トップ・削除用トークンでの削除・利用規約・プライバシーポリシー・お問い合わせ）
│   │   ├── Auth/           # Discord ログイン・初回のログイン設定
│   │   ├── Dashboard/      # dash.example.com（ダッシュボード・リンク詳細・設定）
│   │   ├── Admin/          # 管理者機能（全URL・ユーザー・予約語・APIキー・サイト設定・お問い合わせ・外部サービス・アップデート）
│   │   ├── Api/V1/         # api.example.com
│   │   ├── Redirect/       # /{コード}（中間ページ・パスワード）と redirect サブドメイン
│   │   ├── Install/        # セットアップ画面
│   │   └── Preview/        # local 専用プレビュー
│   ├── Middleware/         # セキュリティヘッダー、セットアップ画面への誘導、API キー認証、アクセス時の定期処理
│   └── Requests/           # 入力検証
├── Installer/              # Web インストーラ
├── Models/
├── Policies/               # 閲覧・編集・削除の権限
├── Rules/                  # 自サービスの URL を拒否する検証
├── Services/
│   ├── Auth/               # Discord OAuth2・ユーザー登録（初代管理者）
│   ├── Account/            # 管理者昇格・退会
│   ├── ShortUrl/           # 発行・コードの照合と空き判定・スラッグ編集・削除用トークン・QRコード
│   ├── Redirect/           # チケット・パスワード試行制限・Safe Browsing・アクセス記録
│   ├── Security/           # reCAPTCHA（Google Cloud の評価 API）
│   ├── Settings/           # 外部サービスのキーの保存
│   ├── Site/               # 利用規約・プライバシーポリシーのひな形
│   ├── Tasks/              # 定期処理（cron 不要の WP-Cron 方式）
│   ├── Dashboard/          # ダッシュボード・統計の表示データ
│   └── Update/             # 自動アップデート（バックアップ・更新方式・ヘルスチェック・通知）
├── Support/                # 設定値・外部サービスのキーの読み出し、短縮URLの組み立て、ナビゲーション
└── ViewModels/             # Blade に渡す readonly なデータ
routes/
├── web.php                 # メイン / ダッシュボード / リダイレクト確認、セットアップ画面
├── api.php                 # api.example.com、定期処理の起動口（/_cron）
├── console.php             # cron を登録した場合の定期実行
└── preview.php             # local 専用プレビュー
resources/templates/legal/  # 利用規約・プライバシーポリシーのひな形（Markdown）
scripts/                    # 配布用 zip の作成
.htaccess / index.php       # 公開フォルダ直下に設置した場合の転送設定・案内ページ
```

## requirements.md との対応

| 章 | 実装 |
|---|---|
| 1-1 | 4 サブドメインを単一アプリで処理（`routes/web.php` `routes/api.php`） |
| 2-1〜2-7 | 発行・予約語・有効期限・編集／削除・パスワード保護・統計・悪意URLチェック |
| 3 | 中間ページ → redirect サブドメインへ自動 POST → 確認 → 移動 |
| 4-1〜4-4 | Discord ログイン、セットアップウィザード、初代管理者・昇格、利用制限、マイページ（ダッシュボード）、退会、reCAPTCHA |
| 5 | 管理者専用 API（API キー、レート制限なし） |
| 6 | 発行時・ダッシュボード・API で QR コード |
| 7 | 自動アップデート（バックアップ 3 世代・ヘルスチェック・ロールバック・Discord 通知・トークンの暗号化保存） |
| 9 | ダッシュボード型、トップと各画面からすぐ発行、レスポンシブ |
| 10 | CSRF・レート制限・SQL インジェクション対策（Eloquent）・reCAPTCHA・ブルートフォース対策・悪意URLチェック・機密値の暗号化 |

### 要件定義からの補足・変更点

requirements.md に記載が無い、または食い違っていた点は次のように決めています。

| 章 | 内容 |
|---|---|
| 2-1 | アクセス時、ランダムコードは大文字小文字を無視、カスタムスラッグは完全一致。カスタムスラッグは既存のランダムコードと大文字小文字違いで重なる値も使えない。使える文字は英数字・`-`・`_` |
| 2-2 | 予約語は大文字小文字を無視して判定。アプリのパス・フォルダと衝突する語（`install` `build` `vendor` など）を初期リストに追加 |
| 2-3 | 未ログイン時の既定の有効期限は 30 日。「期限が近い」表示は残り 3 日以内 |
| 2-4 | カスタムスラッグを編集した場合も、変更前のコードは欠番として再利用しない。編集はログインユーザーの自分のリンク（管理者は全リンク） |
| 2-5 | パスワードは 4〜72 文字。ロックアウトは「リンク × アクセス元 IP」単位（第三者によるリンクの締め出しを防ぐため） |
| 2-6 | リファラはホスト名のみ、IP は保存しない。国は DB-IP の無料データベースを自動取得して判定（MaxMind GeoLite2 を置けばそちらを優先）。クリックは JavaScript を実行したブラウザのみ数える（リンクプレビューのボットを除外） |
| 2-7 | 危険 → 停止、確認不能 → 警告して利用者が選択 |
| 4-2 | セットアップ画面は WordPress と同様に保護なし。入力は DB 接続情報のみで、Discord のキーは初回ログイン時の設定画面（管理者が登録されるまで有効）で入力する。管理者が一人もいなくなる権限変更・退会はできない |
| 4-3 | 月間件数は日本時間の暦月で数え、削除分も含める。レート制限は発行に成功した回数のみ数える。管理者もログインユーザーと同じ上限（API は除く） |
| 4-4 | reCAPTCHA（スコアベース。Google Cloud の評価 API で判定）を未ログインの発行にのみ適用。Google 側の障害時は発行を止めない。退会時、発行済みの短縮URLは残すか削除するかを選べる |
| 5 | API はレート制限に加えて月間上限も適用しない（管理者の他プロジェクトからの発行専用のため） |
| 7-1 | 定期実行は cron を必須とせず、アクセスをきっかけに実行する（WP-Cron 方式）。cron を登録した場合はそちらを優先する |
| 7-1 | Git で設置した環境は要件どおり git / Composer で更新し、配布用 zip で設置した環境は zip の入れ替えで更新する。`mysqldump` が使えない場合は PHP でダンプする |
| 7-2 | GitHub トークンは要件どおり暗号化して DB に保存。ただし DB 接続情報は DB 自体に置けないため `.env` に保存する |
## トラブルシューティング

### セットアップ・運用時

| 表示される内容 | 原因と対処 |
|---|---|
| 「vendor フォルダが見つかりません」 | リポジトリのソースを設置している。Releases の配布用 zip を設置する |
| 「設置設定を確認してください」 | `.htaccess` の転送が効いていない。mod_rewrite と AllowOverride を有効にするか、公開フォルダを `public/` にする |
| 「セットアップを開始できません」（フォルダに書き込めません） | `storage/` 配下と `bootstrap/cache/` のパーミッションを 755（環境によっては 775）にする |
| 「設定ファイルが外部から見えないこと」が **NG** | `.env` などが外から読める状態。上と同じく `.htaccess` の設定か公開フォルダを見直す（解決するまで先へ進めない） |
| 同じ項目が **要確認** | サーバーが自分自身へアクセスできない環境。表示されたリンクを開き、ファイルの中身が表示されないことを確認してチェックを入れる |
| 「ページの有効期限が切れました」（419） | 最初にアクセスしたときと違うスキーム（http / https）で開いている。最初と同じ URL で開き直す |
| 「Discord ログインが設定されていません」 | 保存済みの Client ID / Secret を読み出せない（`.env` の APP_KEY を変更した等）。APP_KEY を元に戻す。管理者がいない状態なら `dash.example.com/login` で設定画面が開く |
| Discord で「Invalid OAuth2 redirect_uri」 | Discord Developer Portal の Redirects に `https://dash.example.com/login/callback` を登録する（設定画面・「外部サービス」に表示される URL をコピー） |
| セットアップで「サブドメインの向き先」が **注意** | サブドメインの公開フォルダが未設定か、DNS・SSL がまだ反映されていない。後から設定しても構わない |
| 自動アップデートが動かない | 「アップデート」画面の「前回の定期処理」、トークン・有効化を確認する。実行履歴にエラー内容が残る。サーバーが自分自身へ接続できない環境（PHP-FPM 以外）では、cron を登録する |

### Windows の開発環境

- **PHP / Composer で `certificate verify failed`**: ウイルス対策ソフトの HTTPS スキャンが独自の証明書で通信を中継していることがあります。Windows が信頼しているルート証明書を CA バンドルに追加し、`php.ini` の `openssl.cafile` と `curl.cainfo` に指定してください（証明書の検証自体は無効にしないこと）。
- **Composer で `Could not delete ... antivirus`**: ウイルス対策ソフトが展開中のファイルをロックしています。除外設定を行うか、配布用 zip は GitHub Actions で作成してください。

## 既知の課題

- design.md のカラートークンには WCAG AA のコントラスト比を満たさない組み合わせがある（プライマリボタンの白文字 2.06:1、注意色 2.73:1 など）
- CSP（Content-Security-Policy）ヘッダーが未設定
- 悪意URLチェックは Safe Browsing Lookup API v4 を使用している。Google が v5 への移行を進めているため、v4 の提供状況は定期的に確認が必要
- レンタルサーバーがリバースプロキシ経由の場合、訪問者の IP（レート制限・国判定・ロックアウトに使用）が正しく取れない可能性がある。その場合は `bootstrap/app.php` で信頼するプロキシを設定する
- 公開フォルダ直下への設置（`.htaccess`）、`mysqldump` の利用、アクセス時の定期処理、自動アップデートは、実サーバー（kagoya）での動作が未検証（requirements.md 8 章の未確認事項）

## ライセンス

[MIT License](LICENSE) で公開しています。フォークして自分のサービスとして運用することも、改変して再配布することもできます。その場合は、「管理」の「アップデート」タブで更新元のリポジトリを自分のものに変更してください。

## セキュリティに関する注意

- `.env`、DB ダンプ、バックアップ、証明書・鍵ファイルはコミットしないでください（`.gitignore` で除外しています）。
- 本番では `APP_ENV=production`・`APP_DEBUG=false` にしてください（セットアップ画面が自動生成する `.env` はこの設定です）。`/_preview` 系ルートは読み込まれなくなります。
- セットアップ画面は完了するまで、Discord ログインの設定画面は管理者が登録されるまで、誰でも操作できます。設置したらすぐに管理者としてログインするところまで進めてください。完了後に `storage/app/private/installed.json` を消すとセットアップ画面が再び開くため、FTP アカウントの管理にも注意してください。
- API キー・GitHub トークン・Webhook URL は第三者に渡さないでください。漏れた場合はダッシュボードで無効化・再設定してください。
- 脆弱性を見つけた場合は Issue ではなく管理者へ直接連絡してください。
