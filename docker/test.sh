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
echo "[3/5] サンプルAPI (sample) をテストしています..."
echo "GET $BASE_URL/wp-json/example/sample/1?name=test"
SAMPLE_RESPONSE=$(curl -s "$BASE_URL/wp-json/example/sample/1?name=test")
echo "レスポンス:"
echo "$SAMPLE_RESPONSE" | python3 -m json.tool 2>/dev/null || echo "$SAMPLE_RESPONSE"

if echo "$SAMPLE_RESPONSE" | grep -q '"id"'; then
    echo "✓ サンプルAPI は正常に動作しています"
else
    echo "❌ サンプルAPI のレスポンスが不正です"
fi

echo ""
echo "[4/5] 投稿一覧API (posts) をテストしています..."
echo "GET $BASE_URL/wp-json/example/posts"
POSTS_RESPONSE=$(curl -s "$BASE_URL/wp-json/example/posts")
echo "レスポンス (最初の500文字):"
echo "$POSTS_RESPONSE" | cut -c 1-500
if echo "$POSTS_RESPONSE" | grep -q '"items"'; then
    echo "✓ 投稿一覧API は正常に動作しています"
else
    echo "⚠️  投稿一覧API のレスポンスを確認してください"
fi

echo ""
echo "[5/5] 静的レスポンスAPI (samplestatic) をテストしています..."
echo "GET $BASE_URL/wp-json/example/samplestatic"
STATIC_RESPONSE=$(curl -s "$BASE_URL/wp-json/example/samplestatic")
echo "レスポンス:"
echo "$STATIC_RESPONSE"
if echo "$STATIC_RESPONSE" | grep -q "Hello"; then
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
echo "  POST詳細:     $BASE_URL/wp-json/example/post/[id]"
echo "  Feed:         $BASE_URL/wp-json/example/feed"
echo "  Swagger UI:   $BASE_URL/wp-admin/admin.php?page=resta-swagger-ui"
echo ""
echo "💡 ヒント:"
echo "  - Swagger UI で全エンドポイントのドキュメントを確認できます"
echo "  - ブラウザで http://localhost:8080/wp-admin にアクセスして管理画面を確認できます"
echo ""
