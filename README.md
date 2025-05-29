# ウェブサイト技術分析レポートツール

![Version](https://img.shields.io/badge/version-1.0.0-blue)

URLを入力すると、そのサイトがどんな技術を使って構築されているかなどを詳細にレポートするWEBアプリケーションです。

## 主な機能 (予定)

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

1.  **リポジトリをクローンします (もしGit管理する場合)**
    ```bash
    git clone <repository-url>
    cd <repository-directory>
    ```

2.  **必要なNode.jsパッケージをインストールします**
    ```bash
    npm install
    ```

3.  **開発環境での実行**
    ```bash
    npm start
    ```

4.  **アプリケーションへのアクセス**
    ブラウザで `http://localhost:8080` を開いて動作を確認します。

### AWS Lightsail環境でのデプロイ

1. **インスタンスの作成**
   * AWS Lightsailコンソールにログインし、Node.jsインスタンスを作成します
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

4. **依存関係のインストールと起動**
   ```bash
   cd /path/to/webpolice
   chmod +x start.sh
   ./start.sh
   ```

5. **永続的な実行のためのプロセス管理**
   PM2を使用してアプリケーションをバックグラウンドで実行し、サーバー再起動時に自動的に起動するよう設定します。
   ```bash
   # PM2のインストール
   npm install -g pm2
   
   # アプリケーションの起動
   pm2 start server.js
   
   # 起動時に自動実行する設定
   pm2 save
   pm2 startup
   # 表示されたコマンドを実行して設定を完了します
   ```

6. **アクセス**
   * ブラウザで `http://<your-instance-ip>:8080` を開いてアプリケーションにアクセスします
   
7. **（オプション）カスタムドメインの設定**
   * DNSレコードを設定して、カスタムドメインをLightsailインスタンスのIPアドレスに向けます
   * Lightsailインスタンスに静的IPを割り当てることをお勧めします
   * HTTPSを有効にするには、Let's Encryptを使用してSSL証明書を設定できます

## 技術スタック

*   バックエンド: Node.js (Express)
*   フロントエンド: HTML, CSS, JavaScript
*   使用ライブラリ:
    *   `express`: Webアプリケーションフレームワーク
    *   `axios`: HTTPリクエスト
    *   `whois-json`: WHOIS情報取得
    *   `cheerio`: HTMLパース
    *   `jspdf` & `html2canvas`: PDF生成
