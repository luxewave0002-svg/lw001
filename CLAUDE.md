# Project Contract — LUXE WAVE (lw001 / test environment)

## Mission
- このリポジトリは luxewave.jp/test/ で稼働する検証環境のソースコードを管理する。
- 優先順位: セキュリティ・データ整合性 > 正確性 > 保守性 > パフォーマンス > 実装速度。
- 後方互換は明示的に変更を求められない限り維持する。
- 最小の一貫した差分で根本原因を直す。無関係な整形・リネーム・リファクタは混ぜない。

## Sources of Truth
- 実際に動いているPHPコード・DBスキーマ・GitHub Actionsのワークフローが一次情報。
- このファイルは「コードから読み取れない、非自明な制約」だけを記録する。
- 推測で要件を埋めない。事実・仮定・提案は区別して報告する。

## Repository Facts
- 言語/実行環境: PHP（サーバー側）+ SQLite（`luxe_wave.sqlite`）
- デプロイ: `main`ブランチへのpush→GitHub Actions（`.github/workflows/deploy-ftp.yml`）→FTPで
  `luxewave.jp/test/` へ自動反映。**deployは自動だが、pushそのものはユーザーの明示確認
  （「プッシュして」または「0」）が無い限り実行しない。**
- DB接続・マイグレーション・共通ヘルパー関数はすべて `db.php` に集約されている
  （セッション、CSRF、ログイン試行制限、Level解除判定、永続ログインCookieなど）。
- `config.php` はGit管理外（`.gitignore`）。SwitchBotのAPIシークレットを含むため、
  デプロイ除外リストにも入れてサーバー上でのみ保持する。
- Git管理外だがサーバー上に必要なもの（deploy-ftp.ymlのexcludeで保護）:
  `sessions/`, `luxe_wave.sqlite`, `config.php`, `upload_test*.*`（Level/Limitedメディア）
- Lint/型チェックに相当するものは無い。構文検証は `php -l ファイル名` で行う。
- 自動テストスイートは無い。動作確認は実際のブラウザ操作のスクリーンショット報告に依存する。

## Standard Operating Loop
1. 対象ファイルを `git fetch && git reset --hard origin/main` で最新化してから着手する
   （ローカルの未コミット変更はこの操作で失われるため、着手直後に行う）
2. 変更が複数ファイル・DBスキーマ・認証/権限・お金に関わる場合は、着手前に方針を一言で共有する
3. 実装後、変更した全PHPファイルに `php -l` を通す
4. 完了報告は「何を・なぜ・どのファイルを・どう検証したか」を書く。未検証は未検証と書く

## Change Discipline
- 新しい抽象化やヘルパー関数を作る前に、`db.php` に同じ責務の実装が無いか探す
- 危険な操作（ユーザー削除、パスワードリセット、メディア削除等）は既存の `confirm()` パターンを踏襲する
- CSRFトークン検証・`htmlspecialchars()`によるエスケープは全フォームで省略しない
- ユーザー入力に依存するSQLは必ずPDOのプレースホルダを使う（生成済みコードは全てこの方針）

## Verification Contract
- 構文チェック: `php -l <file>`（全変更ファイル必須）
- push後はGitHub Actionsのrun conclusionを確認する（`success`まで見届ける）
- 見た目・挙動の最終確認はユーザーのスクリーンショット報告を待つ
- 「動作した」「直った」は実際に確認できた範囲でのみ書く。推測で成功と書かない

## Security and Data Boundaries
- `config.php` の中身（APIキー・シークレット）を出力・ログ・コミットに含めない
- ユーザーのメールアドレス存在有無が外部から推測できる応答をしない
  （パスワード再発行等は登録有無に関わらず同一メッセージ）
- ログイン系エンドポイントは `tooManyFailedAttempts()` によるブルートフォース対策を維持する
- 管理者権限が必要な操作は `admin.php` のセッションガード（`$_SESSION['is_admin']`）を経由する
- 秘密情報の値そのものを変更する操作（トークン再発行等）は、明示的に依頼されない限り行わない

## Git Safety
- pushは毎回、ユーザーの明示確認（「プッシュして」または「0」）を得てから実行する
- `git reset --hard` はリモート最新化の目的以外で使わない。使用前に未pushの変更が無いか確認する
- force push・履歴改変は行わない
- commit前に `git status` で対象ファイルが意図通りか確認する

## Context Management
- 過去の実装判断（例: セッション永続化の設計、無音音声ループの経緯）はチャット履歴に残っているので、
  同じ議論を繰り返す前に経緯を踏まえる
- 一時的な調査用ファイル（`_check_*.php` 等）は用途が終わったら削除する
- 同じ不具合が2回起きたら、場当たり対応ではなく仕組み側（除外リスト・共通関数等）を直す

## Decision Policy
- 安全・可逆・影響範囲が明確な変更は、仮定を一言添えて進めてよい
- 不可逆操作（シークレットのローテーション、DB破壊的変更、公開範囲の変更）は必ず確認を取る
- 「ついで」で無関係な変更を混ぜない。依頼された範囲に留める

## Final Response Contract
1. 何をなぜ変えたか（日本語、簡潔に）
2. 変更したファイル
3. 検証内容（`php -l` の結果、pushの成否）
4. 未確認・要フォローアップの点があれば明記

## Known Recurring Patterns
- **セッション切れ時のCSRF誤判定**: PHPセッションが（特にiOSの長時間バックグラウンド等で）失効した状態で
  フォームPOSTすると、CSRF検証が「不正リクエスト」として扱われる。`mobile_level.php`/`index.php`の
  Level解除フォームで先に発覚・修正し、後から`admin.php`のログイン・全操作フォームでも同型のバグが見つかった。
  修正パターン: `die()`で強制終了せず、`header("Location: ...?session_expired=1")`で戻し、
  案内メッセージを表示する。**新しくPOSTフォームを追加する際は、同じ死角がないか確認すること。**
- Level/Limitedのアップロード済みメディア（`upload_test*.*`）や `sessions/`, `config.php`,
  `luxe_wave.sqlite` など「Git管理外だがサーバーに必要なファイル」を追加した場合、
  `deploy-ftp.yml` のexclude設定に入れ忘れると次回pushで消える。新しい非Git管理ファイルを
  サーバーに置く際は、必ずexclude設定を確認・追加する。

- **Service Workerの適用範囲に注意**: SWは登録したページだけでなく、スコープ内（`/test/`配下）の全ページを管理する。
  PWA化で入れた `sw.js` が `admin.php` のアップロードにも影響し得た（iOS 26.5系のWebKit不具合報告
  #319985/#320904: SW管理下のファイル送信が空ボディになる）。**SWを再導入する場合は、POST/ファイル送信を
  横取りしない設計にし、iOS実機でアップロードを確認すること。**
- GitHub Actionsのscheduleは実行間隔が保証されない（実測で数時間おき）。時間制御はリクエスト時チェックを主、cronは補助にする

## Change Log
- 2026-09-26: `admin.php` のCSRF検証失敗時の挙動を `die()` → 案内メッセージ付きリダイレクトに変更
  （セッション切れ時にアップロード等の操作が無反応に見える問題への対応）
- 2026-09-26: `logs` テーブルに `user_agent` を追加。`writeLog()` が端末・ブラウザ情報も記録するように
  （不具合発生時の環境特定のため。Activity LogsのCSV/画面表示・検索にも反映）

- 2026-09-30: 特定のiOS端末でのみLevel Mediaアップロードが失敗する件の切り分けのため、admin.phpの
  CSRF失敗時に理由コード（E-POST_EMPTY / E-TOKEN_MISSING / E-TOKEN_MISMATCH）を表示・ログ記録するよう変更。
  併せて管理画面にキャッシュ禁止ヘッダーを付与（Safariが古いトークン入りのフォームを復元するのを防ぐ）。v2026.09.30.4
  ※原因は未確定。次回失敗時のコードで確定させる
- 2026-10-01: 上記の結果が E-TOKEN_MISSING（空ボディ）だったため、WebKitの既知不具合報告を根拠にService Workerを廃止。
  sw.jsを自己解除版に置換し、全PWAページ＋admin.phpに「残存SWの解除コード」を追加。判定ロジックも改善。v2026.10.01.1
  ※SWが原因かは仮説。端末での再テスト結果で確定させる
- 2026-10-01: admin.php Activity Logsをスマホ幅ではアコーディオン表示（概要1行→タップで詳細）に変更。
  User Agentは「iPhone · Safari 26.6.2」形式に短縮（全文は展開時/PCではtitle属性）。固定ヘッダーの透けも解消。v2026.10.01.2
- 2026-10-07: 24時間強制ログアウト。ログイン時刻(login_at)をセッションとremember_tokensに持たせ、24h経過で次回アクセス時にログアウト
  （ON中の技術発生は期限時刻で終了・履歴はlogout）。cron_tick.phpは時刻比較をPHP側に変更しエラー時HTTP 500、ワークフローは失敗を握りつぶさない。
  管理者ログイン(admin.php)は対象外。
- 2026-10-07: Level.1のみテスト実装：技術発生ON中は level_sound.m4a をループ再生（level_sound.js、mobile_level.php/index.php）。OFF中は従来の無音ループ。
  iOS実機での再生・ループ継ぎ目は未検証。

## Maintenance
- 同じ理由で2回説明した内容は、このファイルに1行で追記する
- 陳腐化した記述（廃止した機能、変更済みの仕様）は削除する
- 200行を超えたら、テーマ別に分割を検討する
- Change Logは直近のものだけ残し、古いものはKnown Recurring Patternsへ要約統合するか削除する
