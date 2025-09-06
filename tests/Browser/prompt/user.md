# コード生成のルール
- $browser は Laravel\Dusk\Browser クラスのインスタンスです
- $browser にチェーンメソッドで処理を追加してコードを生成してください
- 適当なテストデータを入力する時は、fake() を使ってください
- click, clickLink, press の後には、必ず pause(500) を実行してください
- JavaScript の使用を禁止します
- formaction の使用を禁止します

# サンプルコード
## サイドメニューにユーザー管理が非表示の時は、管理トップをクリックした後に、ユーザー管理をクリックする
```php
        try {
            $browser->clickLink('ユーザー管理')->pause(500);
        } catch (\Throwable $e) {
            $browser->clickLink('管理トップ')->pause(500)->clickLink('ユーザー管理')->pause(500);
        }
```

## 確認ボタンをクリックして、モーダル内の新規登録ボタンをクリックする
```php
        $browser->click('button[data-bs-target="#modalStore"]')->pause(500)
            ->whenAvailable('#modalStore', function ($modal) {
                $modal->click('button.btn.btn-primary')->pause(500);
            });
```

## ユーザーIDに $email, パスワードとパスワード（確認）に $password を入力する
```php
        $browser->type('email', $email)
            ->type('password', $password)
            ->type('repassword', $password);
```

## 画面の一番下までスクロールして、Dropzone に base_path('tests/Browser/upload/logo.png') をアップロードする
```php
        $browser->scrollIntoView('footer')->pause(500)
            ->attach('input.dz-hidden-input', base_path('tests/Browser/upload/logo.png'));
```

## 検索フィールドに 椎名林檎 と入力して、エンターする
```php
            $browser->type('input[name="search_query"]', '椎名林檎')
                ->keys('input[name="search_query"]', \Facebook\WebDriver\WebDriverKeys::ENTER)
                ->pause(1000);
```
