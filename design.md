# chok.ooo デザインシステム（design.md）

- 対象: chok.ooo（トップページ／短縮URL発行フォーム）、dash.chok.ooo（ダッシュボード）
- 関連ドキュメント: `requirements.md`
- UI試作（ビジュアル参考）: https://claude.ai/artifact/RpEVMC1pTsxrj8Usd2whJC
- レイアウト参考: 天音結歌（amane-yuika.choko1229.net）のヘッダー・ヒーロー・機能紹介カードの構成を踏襲
- コンセプト: ポップ・水色・シンプル

---

## 1. カラートークン

| トークン名 | 値 | 用途 |
|---|---|---|
| `color-primary` | `#2EC5E0` | メインアクセント（ボタン、ロゴアイコン背景、アクティブ状態） |
| `color-primary-dark` | `#0891B2` | ホバー時、アイコンのストローク色、リンク色 |
| `color-primary-darker` | `#067A98` | リンクのホバー色 |
| `color-primary-tint` | `#E3F7FC` | バッジ背景、アイコン背景の薄色 |
| `color-primary-tint-soft` | `#F4FBFF` | ページ全体の背景色 |
| `color-border` | `#E7F5FA` | カードの枠線 |
| `color-border-strong` | `#DFF3FB` | ヘッダー等の区切り線 |
| `color-border-input` | `#D7EFF7` | 入力フィールドの枠線 |
| `color-text-primary` | `#0F2A3D` | 見出し・本文の基本色 |
| `color-text-secondary` | `#5E7A87` | サブテキスト・ラベル |
| `color-text-muted` | `#94A9B3` | 無効化状態・補足テキスト |
| `color-dark-panel` | `#0F2A3D` | 発行結果表示など、コントラストを効かせるダークパネル背景 |
| `color-dark-panel-button` | `#1C3F52` | ダークパネル内のボタン背景 |
| `color-accent-warm` | `#FFD166` | アバターの背景等、アクセントの差し色（多用しない） |
| `color-success` | `#0891B2`（プライマリと共用）| 「無期限」等の安全状態 |
| `color-warning` | `#D98C00` | 「有効期限が近い」等の注意状態 |
| `color-danger` | `#D64545` | 削除アイコン、「期限切れ」表示 |
| `color-white` | `#FFFFFF` | カード・ボタン背景 |

**配色ルール**: アクセントは水色（primary）1色に絞り、暖色（`color-accent-warm`）は装飾的なワンポイントのみに限定して使う。danger/warningは状態表示にのみ使用し、装飾には使わない。

---

## 2. タイポグラフィ

| 用途 | フォント | ウェイト | 備考 |
|---|---|---|---|
| 見出し・ボタン・数値強調 | `M PLUS Rounded 1c` | 700〜800 | 丸みのあるポップな印象。LINE Seedの代替として採用（Web配信フォントが手に入り次第差し替え可） |
| 本文・ラベル・表データ | `Noto Sans JP` | 400〜500 | 可読性重視のシンプルな本文用 |

```css
/* Google Fonts 読み込み例 */
@import url('https://fonts.googleapis.com/css2?family=M+PLUS+Rounded+1c:wght@500;700;800&family=Noto+Sans+JP:wght@400;500;700&display=swap');
```

### フォントサイズスケール

| 用途 | サイズ | ウェイト |
|---|---|---|
| ヒーロー見出し(H1) | 52px | 800 |
| セクション見出し | 20px | 700 |
| カード見出し | 15〜16px | 700 |
| 本文 | 14〜16px | 400〜500 |
| 補足・キャプション | 12〜13px | 400〜500 |
| 統計数値（強調） | 32px | 800 |

---

## 3. スペーシング・角丸・シャドウ

| トークン | 値 |
|---|---|
| 角丸（カード） | 18〜24px |
| 角丸（ボタン・入力） | 12〜14px |
| 角丸（ピル/バッジ） | 999px（完全な丸） |
| カードシャドウ | `0 8px 24px rgba(15,42,61,0.06)` 〜 `0 12px 32px rgba(15,42,61,0.08)` |
| 標準パディング（カード内） | 24〜32px |
| セクション間の余白 | 60〜96px |

---

## 4. コンポーネントパターン

### ボタン
- **プライマリボタン**: 背景 `color-primary`、文字 白、`M PLUS Rounded 1c` 700、角丸14px、パディング `16px 28px`
- **セカンダリボタン（枠線）**: 背景 白、枠線1.5px `color-primary`、文字 `color-text-primary`

### カード
- 背景白、枠線1px `color-border`、角丸18px、内側パディング24〜26px

### 入力フィールド
- 枠線1.5px `color-border-input`、角丸12〜14px、パディング `14px〜16px 18px〜20px`、フォーカス時は枠線を`color-primary`に変更（実装時に追加）

### バッジ・チップ
- 角丸999px（ピル型）、背景 `color-primary-tint`、文字 `color-primary-dark`、太字13px

### テーブル（発行履歴）
- ヘッダー行背景 `#F7FCFE`、文字 `color-text-secondary` 12px
- 行の区切りは1px `#EEF7FB` の上ボーダーのみ（縦線なし）
- 状態表示: 無期限=`color-text-secondary`、期限が近い=`color-warning`、期限切れ=`color-danger`

### アイコン
- 塗りつぶしなし・ストローク（stroke-width: 2）のシンプルなラインアイコンで統一
- 絵文字は使用しない
- カラーは基本 `color-text-secondary`、削除など破壊的操作のみ `color-danger`

---

## 5. レイアウト原則

- コンテンツ最大幅: フォーム・ヒーロー部は`720px`中央寄せ、ダッシュボード全体は`1280px`基準
- グリッド: 機能紹介カードは4カラム、統計カードは3カラム（`grid-template-columns: repeat(N, minmax(0,1fr))`）
- レスポンシブ: スマホでは上記グリッドを1カラムに、テーブルは横スクロール対応にする（要件定義書 第9章の通りレスポンシブ必須）

---

## 6. 参考実装（ビジュアル試作）

以下のURLで実際のレイアウト・配色を確認できます（このチャット上で作成したHTMLモックアップ）。

https://claude.ai/artifact/RpEVMC1pTsxrj8Usd2whJC

- Main.dc.html: トップページ（chok.ooo）
- Dashboard.dc.html: ダッシュボード（dash.chok.ooo）
