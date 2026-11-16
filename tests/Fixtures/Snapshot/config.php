<?php
/**
 * スナップショットテスト用の設定。SnapshotTest と scripts/generate-openapi.php の両方がこれを読む
 */

return [
    'routeDirectory' => [
        [__DIR__ . '/Routes', 'Test\\Resta\\Fixtures\\Snapshot\\Routes\\', 'snapshot'],
    ],
    'schemaDirectory' => [
        [__DIR__ . '/Schemas', 'Test\\Resta\\Fixtures\\Snapshot\\Schemas\\'],
    ],
];
