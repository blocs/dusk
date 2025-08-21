# サンプルコード
## サイドメニューのユーザー管理が非表示の時は、サイドメニューの管理トップをクリックしてからユーザー管理を開く
```php
            try {
                $browser->clickLink('ユーザー管理');
            } catch (\Throwable $e) {
                $browser->clickLink('管理トップ')->pause(200)->clickLink('ユーザー管理');
            }
```

## 確認ボタンをクリックした後に、モーダル内の新規登録ボタンを押す
```php
            $browser->click('button[data-bs-target="#modalStore"]')
                ->whenAvailable('#modalStore', function ($modal) {
                    $modal->click('button[formaction="http://localhost/admin/user"]');
                });
```

## ユーザーIDに適当なemailを入力する
```php
            $browser->type('email', fake()->email());
```

## Dropzone に logo.png アップロードする
```php
            $browser->attach('input.dz-hidden-input', base_path('tests/Browser/logo.png'));
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
