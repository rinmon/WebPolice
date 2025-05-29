# ウェブサイト技術分析レポートツール

![Version](https://img.shields.io/badge/version-1.1.0-blue)
[![GitHub](https://img.shields.io/github/stars/rinmon/webpolice?style=social)](https://github.com/rinmon/webpolice)

URLを入力すると、そのサイトがどんな技術を使って構築されているかなどを詳細にレポートするWEBアプリケーションです。

## 主な機能

*   完全日本語版
*   いつから存在するか (Wayback Machine APIなどを利用)
*   WHOIS等のドメイン情報
*   DNSレコード情報 (A, AAAA, MX, NS, CNAME, TXT等)
*   サーバー情報 (IPアドレス、国、ISP)
*   WEBサイト自体の技術レポート (使用フレームワーク、CMS、ライブラリ等)
*   SEO情報 (基本的なメタタグ、キーワードなど)
*   インターネット上でのランキング的な情報 (可能な範囲で)
*   その他、WEBサイトとして必要、分析可能な項目をカテゴリ別に整理して表示
*   得られた情報を保存する機能
*   PDFにしてダウンロードできる機能

## セットアップと実行

### ローカル環境

1.  **リポジトリをクローンします**
    ```bash
    git clone https://github.com/rinmon/webpolice.git
    cd webpolice
    ```

2.  **PHPバージョンを使用する場合**
    * PHPがインストールされていることを確認
    * Webサーバー（Apache、Nginxなど）にファイルを配置するか、PHPの内蔵サーバーを使用
    ```bash
    # PHPの内蔵サーバーを使用する場合
    php -S localhost:8080
    ```

3.  **Node.jsバージョンを使用する場合（従来版）**
    ```bash
    # 必要なパッケージをインストール
    npm install
    
    # サーバー起動
    node server.js
    ```

4.  **アプリケーションへのアクセス**
    ブラウザで `http://localhost:8080` を開いて動作を確認します。

### AWS Lightsail環境でのデプロイ

1. **インスタンスの作成**
   * AWS Lightsailコンソールにログインし、**Bitnami PHP**インスタンスを作成します
   * 少なくとも1GB RAM以上のプランを選択することをお勧めします

2. **ファイアウォール設定**
   * AWS Lightsailコンソールでインスタンスを選択し、「ネットワーキング」タブを開きます
   * 「ファイアウォール」セクションで、TCP 8080ポートを開放します（アプリケーションが使用するポート）

3. **アプリケーションのデプロイ**
   * SSHを使用してLightsailインスタンスに接続します
   * アプリケーションファイルをインスタンスに転送します（SFTPまたはGitを使用）
   ```bash
   # 例：SFTPを使用する場合
   sftp -i <your-key.pem> bitnami@<your-instance-ip>
   # または、Gitを使用する場合
   git clone <repository-url>
   ```

4. **デプロイ**
   ```bash
   # Bitnami PHPインスタンスのApacheドキュメントルートにファイルを配置
   # 通常は /opt/bitnami/apache2/htdocs
   cd /opt/bitnami/apache2/htdocs
   git clone https://github.com/rinmon/webpolice.git
   
   # 必要なパーミッションを設定
   chmod 755 -R webpolice
   chmod 777 -R webpolice/tmp
   ```

5. **アクセス**
   * ブラウザで `http://<your-instance-ip>/webpolice` を開いてアプリケーションにアクセスします

6. **（オプション）独自ドメインの設定**
   * DNSレコードを設定して、カスタムドメインをLightsailインスタンスのIPアドレスに向けます
   * Lightsailインスタンスに静的IPを割り当てることをお勧めします
   * HTTPSを有効にするには、Let's Encryptを使用してSSL証明書を設定できます
   


## 技術スタック

### PHPバージョン（推奨）

*   バックエンド: PHP 7.4+
*   フロントエンド: HTML, CSS, JavaScript
*   使用ライブラリ:
    *   `FPDF`: PDF生成

### Node.jsバージョン（従来版）

*   バックエンド: Node.js (Express)
*   フロントエンド: HTML, CSS, JavaScript
*   使用ライブラリ:
    *   `express`: Webアプリケーションフレームワーク
    *   `axios`: HTTPリクエスト
    *   `whois-json`: WHOIS情報取得
    *   `cheerio`: HTMLパース
    *   `jspdf` & `html2canvas`: PDF生成
