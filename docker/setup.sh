#!/bin/bash
set -e

echo "=========================================="
echo "WordPress + wp-resta セットアップスクリプト"
echo "=========================================="

# Docker コンテナの起動
echo ""
echo "[1/6] Docker コンテナを起動しています..."
docker compose up -d

# WordPress が起動するまで待機
echo ""
echo "[2/6] WordPress の起動を待っています..."
sleep 10

# WordPress のコアインストールチェック
echo ""
echo "[3/6] WordPress のインストール状況を確認しています..."
if docker compose exec -T wpcli wp core is-installed 2>/dev/null; then
    echo "✓ WordPress は既にインストールされています"
else
    echo "WordPress をインストールしています..."
    docker compose exec -T wpcli wp core install \
        --url="http://localhost:8080" \
        --title="Wp-Resta Development" \
        --admin_user="admin" \
        --admin_password="admin" \
        --admin_email="admin@example.com" \
        --skip-email
    echo "✓ WordPress のインストールが完了しました"
fi

# パーマリンク設定
echo ""
echo "[4/6] パーマリンク設定を「投稿名」に変更しています..."
docker compose exec -T wpcli wp rewrite structure '/%postname%/' --hard
docker compose exec -T wpcli wp rewrite flush --hard
echo "✓ パーマリンク設定が完了しました"

# プラグインの有効化
echo ""
echo "[5/6] wp-resta プラグインを有効化しています..."
if docker compose exec -T wpcli wp plugin is-active wp-resta 2>/dev/null; then
    echo "✓ wp-resta プラグインは既に有効化されています"
else
    docker compose exec -T wpcli wp plugin activate wp-resta
    echo "✓ wp-resta プラグインの有効化が完了しました"
fi

# テスト用の投稿を作成
echo ""
echo "[6/6] テスト用の投稿を作成しています..."
POST_ID=$(docker compose exec -T wpcli wp post list --post_type=post --format=ids | head -n 1)
if [ -z "$POST_ID" ]; then
    POST_ID=$(docker compose exec -T wpcli wp post create \
        --post_type=post \
        --post_title="テスト投稿" \
        --post_content="これはテスト投稿です。" \
        --post_status=publish \
        --porcelain)
    echo "✓ テスト投稿を作成しました (ID: $POST_ID)"
else
    echo "✓ テスト用の投稿が既に存在します (ID: $POST_ID)"
fi

# 完了メッセージ
echo ""
echo "=========================================="
echo "✓ セットアップが完了しました！"
echo "=========================================="
echo ""
echo "📝 アクセス情報:"
echo "  WordPress: http://localhost:8080"
echo "  管理画面:   http://localhost:8080/wp-admin"
echo "    ユーザー名: admin"
echo "    パスワード: admin"
echo ""
echo "📚 REST API エンドポイント:"
echo "  サンプルAPI: http://localhost:8080/wp-json/example/sample/1"
echo "  Swagger UI:  http://localhost:8080/wp-admin/admin.php?page=resta-swagger-ui"
echo ""
echo "🧪 API をテストするには:"
echo "  ./docker/test.sh"
echo ""
echo "🛑 環境を停止するには:"
echo "  docker compose down"
echo ""
