# 設計の決定の記録（ADR）

設計上の決定を、1 つにつき 1 ファイルで記録する。決定を導く考え方は [design.md](../design.md) にある。

## 一覧

| 番号 | 決定 | 状態 | 関連 |
|---|---|---|---|
| [0001](0001-record-decisions.md) | 設計の決定を ADR に記録する | 採用 | |
| [0002](0002-callback-definition.md) | callback の定義: 戻り値は body、status とヘッダは `ResponseContext`、引数は 3 種類だけ | 採用（0.13） | #99 |
| [0003](0003-remove-validate-authorize.md) | `validate()` / `authorize()` を削除し、`Principal` は必要なクラスだけが知る | 採用（0.13） | #114 |
| [0004](0004-authorization-attributes.md) | 許可制の操作を `#[Authenticated]` / `#[Permission]` で宣言する | 採用（0.13） | #116 |
| [0005](0005-static-route-body.md) | 静的なルートは `public readonly` の `body` で宣言する | 採用（0.13） | #105 |

## 書きかた

- ファイル名は `NNNN-slug.md`。番号は連番
- 見出しは「背景」「決定」「採らなかった案」「帰結」。短く書く。議論の過程は issue に残し、ここには結論と理由だけを書く
- 状態は「採用」「置き換え済み」。まだ実装していない決定は、実装するバージョンを添える
- **書いた ADR は書き換えない。** 決定を覆すときは新しい ADR を書き、古いほうの状態を「置き換え済み（NNNN）」にする
- 対象は、選択肢の中から 1 つを選んだ決定。判断の基準や考え方は design.md に書く
