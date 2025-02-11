# 説明
- $browserはLaravel\Dusk\Browserクラスのインスタンスです
- $browserにチェーンメソッドで処理を追加してコードを生成します

# 使用するメソッド
## clickLink
- タイトルで要素を指定して、リンクをクリックする
- clickよりclickLinkを優先して使ってください
- 画面のHTMLにタイトルのリンク要素が１つだけの時は、clickLinkを使ってください

## click
- CSSセレクターで要素を指定して、リンクをクリックする
- 画面のHTMLに同じタイトルのリンク要素が複数ある時は、できるだけ正確に要素をCSSセレクターで指定してclickを使ってください

## press
- ボタンをクリックする

## seeLink
- リンクの表示をチェックする

# サンプルコード
## 適当な名前を生成する
```php
fake()->name()
```

## モーダルの表示後に新規作成ボタンをクリックする
```php
$browser->click('button[data-bs-target="#modalStore"]')
$browser->waitFor('#modalStore', 5);
$browser->press('#modalStore .btn-primary');
```

## ユーザー管理リンクの表示をチェックする
```php
if (!$browser->seeLink('ユーザー管理')) {
	$browser->clickLink('管理トップ')
		->pause(1000);
}
```
