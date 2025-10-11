<div align="center"><img src="logo.svg" width="400" /></div>

# Laravel dusk browser test support tool
Laravelのブラウザテストサポートツール

[![Latest stable version](https://img.shields.io/packagist/v/blocs/dusk)](https://packagist.org/packages/blocs/dusk)
[![Total downloads](https://img.shields.io/packagist/dt/blocs/dusk)](https://packagist.org/packages/blocs/dusk)
[![GitHub code size](https://img.shields.io/github/languages/code-size/blocs/dusk)](https://github.com/blocs/dusk)
[![GitHub license](https://img.shields.io/github/license/blocs/dusk)](https://github.com/blocs/dusk)
[![Laravel awesome](https://img.shields.io/badge/Awesome-Laravel-green)](https://github.com/blocs/dusk)
[![Laravel version](https://img.shields.io/badge/laravel-%3E%3D11-green)](https://github.com/blocs/dusk)
[![PHP version](https://img.shields.io/badge/php-%3E%3D8.3-blue)](https://github.com/blocs/dusk)

# 概要 | Overview
生成AIで、Laravel Dusk のテストコード作成をサポートするツール

A tool that uses generative AI to assist in creating Laravel Dusk test code.

本パッケージは、テスト対象の Laravel プロジェクトに直接インストールする必要はありません。テスト対象のプロジェクトがブラウザーからアクセス可能（開発サーバーまたはステージング環境で稼働）であれば、別途用意したテスト用の Laravel プロジェクトからテストを実行できます。

You do not need to install this package directly in the target Laravel project. If the target project is accessible via a browser (running on a development server or staging environment), you can set up a separate Laravel project for testing and execute the tests from there.

# 導入方法 | Setup

## 1. テスト用の Laravel プロジェクト作成
```bash
composer create-project laravel/laravel dusk-web-test
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

## 4. Laravel Dusk を実行
```bash
php artisan dusk
```
初回実行時にエラーが発生する場合があります。その場合は、再度実行してください。

## 5. envの設定
```bash
OPENAI_API_KEY=your-api-key-here
```
テストコードの生成には LLM の利用が必要なため、お持ちでない場合は、OpenAI_API キーを取得してください。  
[OPENAI_APIキーの取得方法](https://qiita.com/kurata04/items/a10bdc44cc0d1e62dad3)

# 使い方
[Qiita:もうテストで疲弊しない！生成AI × Laravel Duskでブラウザテストコードを自動生成してくれる話](https://qiita.com/yokoba/items/06eb5b61bdd8c602c95d)
