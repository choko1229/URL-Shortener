#!/usr/bin/env bash
#
# 配布用 zip を作成する。サーバーで Composer / Node.js を使わずに設置できるよう、
# vendor/ とビルド済みアセット（public/build）を同梱し、実行に不要なファイルを除く。
#
# 使い方:
#   bash scripts/build-release.sh [バージョン名]
#   例) bash scripts/build-release.sh v26.9.0
#
# 環境変数:
#   PHP_BIN       PHP の実行ファイル（既定: php）
#   COMPOSER_BIN  Composer の実行コマンド（既定: composer。例: "php /path/to/composer.phar"）
#
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
VERSION="${1:-$(git -C "$ROOT" describe --tags --always --dirty)}"
VERSION="${VERSION//\//-}"
PHP_BIN="${PHP_BIN:-php}"
COMPOSER_BIN="${COMPOSER_BIN:-composer}"

WORK_DIR="$ROOT/build/release"
PACKAGE_NAME="chok-ooo"
STAGE_DIR="$WORK_DIR/$PACKAGE_NAME"
OUTPUT="$WORK_DIR/$PACKAGE_NAME-$VERSION.zip"

echo "==> 作業ディレクトリを準備しています"
rm -rf "$WORK_DIR"
mkdir -p "$STAGE_DIR"

if [ -n "$(git -C "$ROOT" status --porcelain)" ]; then
    echo "    注意: 未コミットの変更も含めて作成します（.gitignore 対象のファイルは含みません）" >&2
fi

# Git 管理下のファイルと、未追跡だが .gitignore 対象ではないファイルだけをコピーする（.env 等は含まれない）
git -C "$ROOT" ls-files -z --cached --others --exclude-standard \
    | tar -C "$ROOT" --null --ignore-failed-read -T - -cf - \
    | tar -C "$STAGE_DIR" -xf -

cd "$STAGE_DIR"

echo "==> 本番用の PHP 依存パッケージをインストールしています"
$COMPOSER_BIN install --no-dev --optimize-autoloader --no-interaction --prefer-dist

echo "==> フロントエンドをビルドしています"
npm ci --no-fund --no-audit
npm run build

echo "==> 実行に不要なファイルを削除しています"
rm -rf node_modules tests scripts .github resources/css resources/js
rm -f package.json package-lock.json vite.config.js phpunit.xml .editorconfig .gitattributes .gitignore ./*.md
# 開発環境で生成されたキャッシュを持ち込まない
find bootstrap/cache -type f ! -name .gitignore -delete

echo "==> zip を作成しています"
"$PHP_BIN" "$ROOT/scripts/zip-directory.php" "$STAGE_DIR" "$OUTPUT" "$PACKAGE_NAME"

echo "==> 完了: $OUTPUT"
