# メソッドルール
## clickLink
- タイトルで要素を指定して、リンクをクリックする
- clickよりclickLinkを優先して使う
- 画面のHTMLにタイトルのリンク要素が１つだけの時は、clickLinkを使う

## click
- CSSセレクターで要素を指定して、リンクをクリックする
- 画面のHTMLに同じタイトルのリンク要素が複数ある時は、できるだけ正確に要素をCSSセレクターで指定してclickを使う
- Element is not clickable エラーの時は、CSSセレクターで指定した要素まで scrollIntoView でスクロールする

## press
- ボタンをクリックする
- できるだけタイトルで要素を指定する
- 画面のHTMLに同じタイトルのボタン要素が複数ある時は、できるだけ正確に要素をCSSセレクターで指定する
- press 実行後に、必ず pause(1000) を実行する

## seeLink
- リンクの表示をチェックする

## waitFor
- waitFor 実行前に、必ず screenshot('waitFor') を実行する

# サンプルコード
## 適当な名前を生成する
```php
fake()->name()
```

## モーダルの表示後に新規作成ボタンをクリックする
```php
$browser->click('button[data-bs-target="#modalStore"]')
	->screenshot('waitFor')->waitFor('#modalStore')
	->press('#modalStore .btn-primary')
	->pause(1000);
```

## ユーザー管理リンクの表示をチェックする
```php
if (!$browser->seeLink('ユーザー管理')) {
	$browser->clickLink('管理トップ')
		->pause(1000);
}
```

## Dropzone に logo.png アップロードする
```php
$browser->attach('input.dz-hidden-input', storage_path('logo.png'));
```

## アバター画像までスクロールして、アバター画像をクリックする
```php
$browser->scrollIntoView('.avatar')
	->pause(1000)
	->click('.avatar');
```

## 検索フィールドに 椎名林檎 と入力して、エンターする
```php
$browser->type('input[name="search_query"]', '椎名林檎')
	->keys('input[name="search_query"]', \Facebook\WebDriver\WebDriverKeys::ENTER)
	->pause(1000);
```
