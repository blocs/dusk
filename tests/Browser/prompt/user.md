# コード生成のルール
- $browser は Laravel\Dusk\Browser クラスのインスタンスです
- $browser にチェーンメソッドで処理を追加してコードを生成してください
- 適当なテストデータを入力する時は、fake() を使ってください
- click, clickLink, press の後には、必ず pause(500) を実行してください
- JavaScript の使用を禁止します
- formaction の使用を禁止します
