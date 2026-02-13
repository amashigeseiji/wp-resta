# Wp\Resta クイックスタートガイド

WordPress REST API 開発フレームワーク wp-resta の動作確認環境を Docker で簡単にセットアップできます。

## 必要な環境

- Docker Desktop または Docker + Docker Compose
- curl（テスト用、オプション）

## セットアップ手順

### 1. 依存パッケージのインストール

```bash
composer install
```

### 2. Docker 環境の起動とセットアップ

```bash
./docker/setup.sh
```

このスクリプトは以下を自動で実行します：

- Docker コンテナの起動（WordPress、MySQL、wp-cli）
- WordPress のインストール
- パーマリンク設定（投稿名形式に変更）
- wp-resta プラグインの有効化
- テスト用投稿の作成

### 3. API の動作確認

```bash
./docker/test.sh
```

すべてのサンプル API エンドポイントをテストし、正常に動作しているか確認します。

## アクセス情報

セットアップ完了後、以下の URL にアクセスできます：

| サービス | URL | 認証情報 |
|---------|-----|---------|
| WordPress サイト | http://localhost:8080 | - |
| WordPress 管理画面 | http://localhost:8080/wp-admin | admin / admin |
| REST API ルート | http://localhost:8080/wp-json | - |
| Swagger UI | http://localhost:8080/wp-admin/admin.php?page=resta-swagger-ui | admin / admin |

## サンプル API エンドポイント

### 1. サンプル API（パラメータ付き）

```bash
curl "http://localhost:8080/wp-json/example/sample/1?name=test&a_or_b=a"
```

**機能:** URL パラメータとクエリパラメータのテスト、WordPress データベースアクセスのデモ

### 2. 投稿一覧 API

```bash
curl "http://localhost:8080/wp-json/example/posts"
```

**機能:** WordPress の投稿データを取得

### 3. 投稿詳細 API

```bash
curl "http://localhost:8080/wp-json/example/post/1"
```

**機能:** 指定 ID の投稿詳細を取得

### 4. 静的レスポンス API

```bash
curl "http://localhost:8080/wp-json/example/samplestatic"
```

**機能:** 静的な値を返すシンプルな API

### 5. Feed API

```bash
curl "http://localhost:8080/wp-json/example/feed/1"
```

**機能:** PSR-7 レスポンスのデモ

## 開発ワークフロー

### コードの変更を反映

プラグインのコードを変更した場合、Docker ボリュームマウントにより自動的に反映されます。
ブラウザをリロードするか、API を再度呼び出すだけで変更が確認できます。

### ログの確認

```bash
# WordPress のログを表示
docker compose logs -f wordpress

# すべてのコンテナのログを表示
docker compose logs -f
```

### wp-cli コマンドの実行

```bash
docker compose exec wpcli wp <command>

# 例: プラグイン一覧を表示
docker compose exec wpcli wp plugin list

# 例: キャッシュをクリア
docker compose exec wpcli wp cache flush
```

### データベースへの直接アクセス

```bash
docker compose exec db mysql -u wordpress -pwordpress wordpress
```

## 環境の管理

### 環境の停止

```bash
docker compose down
```

### 環境の完全削除（データベースも含む）

```bash
docker compose down -v
```

### 環境の再起動

```bash
docker compose restart
```

## トラブルシューティング

### ポート 8080 が既に使用されている場合

`docker-compose.yml` のポート番号を変更してください：

```yaml
wordpress:
  ports:
    - "8081:80"  # 8080 を別のポート番号に変更
```

### プラグインが有効化できない場合

1. Composer の依存関係が正しくインストールされているか確認：

```bash
composer install
```

2. 設定ファイルが正しく配置されているか確認：

```bash
ls -la docker/config.php
```

3. コンテナを再起動：

```bash
docker compose down
./docker/setup.sh
```

### API が 404 を返す場合

パーマリンク設定を確認してください：

```bash
docker compose exec wpcli wp rewrite flush --hard
```

## 次のステップ

1. **サンプルコードを確認:** `src/REST/Example/Routes/` ディレクトリのサンプル実装を参照
2. **Swagger UI を確認:** http://localhost:8080/wp-admin/admin.php?page=resta-swagger-ui で API ドキュメントを確認
3. **独自の API を作成:** README.md の「How to develop」セクションを参照して、独自のエンドポイントを作成

## 参考資料

- [README.md](README.md) - 詳細なドキュメント
- [config-sample.php](config-sample.php) - 設定ファイルのサンプル
- [サンプル実装](src/REST/Example/Routes/) - 実装例
