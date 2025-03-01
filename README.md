<div align="center"><img src="logo.svg" width="400" /></div>

# Laravel dusk browser test support tool
Laravelのブラウザテストサポートツール

[![Latest stable version](https://img.shields.io/packagist/v/blocs/dusk)](https://packagist.org/packages/blocs/dusk)
[![Total downloads](https://img.shields.io/packagist/dt/blocs/dusk)](https://packagist.org/packages/blocs/dusk)
[![GitHub code size](https://img.shields.io/github/languages/code-size/blocs/dusk)](https://github.com/blocs/dusk)
[![GitHub license](https://img.shields.io/github/license/blocs/dusk)](https://github.com/blocs/dusk)
[![Laravel awesome](https://img.shields.io/badge/Awesome-Laravel-green)](https://github.com/blocs/dusk)
[![Laravel version](https://img.shields.io/badge/laravel-%3E%3D10-green)](https://github.com/blocs/dusk)
[![PHP version](https://img.shields.io/badge/php-%3E%3D8.1-blue)](https://github.com/blocs/dusk)

# 概要
生成AIで、Laravel Dusk のテストコード作成をサポートするツール

# 導入方法
本パッケージを使用する際、テスト対象の Laravel プロジェクトに直接インストールする必要はありません。テスト対象のプロジェクトがブラウザからアクセス可能な状態（開発サーバーやステージング環境として起動している状態）であれば、別途テスト専用の Laravel プロジェクトを用意し、そこからテストを実行することも可能です。

※テストコードの生成には LLM の利用が必要なため、お持ちでない場合は、OpenAI_API キーを取得してください。

[OPENAI_APIキーの取得方法](https://qiita.com/kurata04/items/a10bdc44cc0d1e62dad3)


## 1. Laravel プロジェクト作成
```bash
composer create-project laravel/laravel [プロジェクト名]
例） composer create-project laravel/laravel dusk-web-test
```

## 2. blocs/dusk をインストール
```bash
cd dusk-web-test
composer require --dev blocs/dusk
```

## 3. Laravel Dusk と Open AI をインストール
```bash
php artisan dusk:install
php artisan openai:install
```

## 4. envの設定
```bash
OPENAI_API_KEY=your-api-key-here
```

# 使い方
[Qiita:「Laravel Dusk × GPT-4o」でブラウザテストを爆速自動化！最強ツールを紹介します](https://qiita.com/hyada/items/c40ae6a8fc6fff05c243)
