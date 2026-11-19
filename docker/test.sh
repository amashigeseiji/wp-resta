#!/bin/bash
set -e

echo "=========================================="
echo "wp-resta API 動作確認スクリプト"
echo "=========================================="

# WordPress が起動しているか確認
if ! docker compose ps wordpress | grep -q "Up"; then
    echo "❌ WordPress コンテナが起動していません"
    echo "次のコマンドで起動してください: ./docker/setup.sh"
    exit 1
fi

BASE_URL="http://localhost:8080"

echo ""
echo "[1/5] WordPress が応答するか確認しています..."
if curl -s -o /dev/null -w "%{http_code}" "$BASE_URL" | grep -q "200\|302"; then
    echo "✓ WordPress は正常に動作しています"
else
    echo "❌ WordPress にアクセスできません"
    exit 1
fi

echo ""
echo "[2/5] REST API ルートを確認しています..."
ROUTES=$(curl -s "$BASE_URL/wp-json/")
if echo "$ROUTES" | grep -q "example"; then
    echo "✓ example ネームスペースが見つかりました"
else
    echo "❌ example ネームスペースが見つかりません"
    echo "プラグインが正しく有効化されているか確認してください"
    exit 1
fi

echo ""
echo "[3/5] 記事 1 件のAPI (post) をテストしています..."
echo "GET $BASE_URL/wp-json/example/post/1"
POST_RESPONSE=$(curl -s "$BASE_URL/wp-json/example/post/1")
echo "レスポンス:"
echo "$POST_RESPONSE" | python3 -m json.tool 2>/dev/null || echo "$POST_RESPONSE"

if echo "$POST_RESPONSE" | grep -q '"ID"'; then
    echo "✓ 記事 1 件のAPI は正常に動作しています"
else
    echo "❌ 記事 1 件のAPI のレスポンスが不正です"
fi

echo ""
echo "[4/5] 投稿一覧API (posts) をテストしています..."
echo "GET $BASE_URL/wp-json/example/posts"
POSTS_RESPONSE=$(curl -s "$BASE_URL/wp-json/example/posts")
echo "レスポンス (最初の500文字):"
echo "$POSTS_RESPONSE" | cut -c 1-500
if echo "$POSTS_RESPONSE" | grep -q '"post_title"'; then
    echo "✓ 投稿一覧API は正常に動作しています"
else
    echo "⚠️  投稿一覧API のレスポンスを確認してください"
fi

echo ""
echo "[5/5] 静的レスポンスAPI (version) をテストしています..."
echo "GET $BASE_URL/wp-json/example/version"
STATIC_RESPONSE=$(curl -s "$BASE_URL/wp-json/example/version")
echo "レスポンス:"
echo "$STATIC_RESPONSE"
if echo "$STATIC_RESPONSE" | grep -q '"version"'; then
    echo "✓ 静的レスポンスAPI は正常に動作しています"
else
    echo "⚠️  静的レスポンスAPI のレスポンスを確認してください"
fi

echo ""
echo "=========================================="
echo "✓ すべてのテストが完了しました"
echo "=========================================="
echo ""
echo "📚 その他のエンドポイント:"
echo "  下書き:       $BASE_URL/wp-json/example/drafts（要ログイン）"
echo "  Swagger UI:   $BASE_URL/wp-admin/admin.php?page=wp-resta"
echo ""
echo "💡 ヒント:"
echo "  - Swagger UI で全エンドポイントのドキュメントを確認できます"
echo "  - ブラウザで http://localhost:8080/wp-admin にアクセスして管理画面を確認できます"
echo ""
