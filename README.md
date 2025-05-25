# ウェブサイト技術分析レポートツール

![Version](https://img.shields.io/badge/version-0.9.0-blue)

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

1.  **リポジトリをクローンします (もしGit管理する場合)**
    ```bash
    # git clone <repository-url>
    # cd <repository-directory>
    ```

2.  **Python仮想環境の作成と有効化 (推奨)**
    ```bash
    python -m venv venv
    source venv/bin/activate  # macOS/Linux
    # venv\Scripts\activate    # Windows
    ```

3.  **必要なライブラリをインストールします**
    ```bash
    pip install -r requirements.txt
    ```

4.  **開発環境での実行**
    *   Flaskアプリケーションを開発モードで実行します。
        ```bash
        python app.py
        ```
    *   `app.py` 内で `app.config['APPLICATION_ROOT'] = '/web-analyzer'` が設定されているため、ブラウザで `http://127.0.0.1:5001/tools/webpolice/` を開いて動作を確認します。

5.  **本番環境でのデプロイ (Gunicorn + Apache リバースプロキシ)**
    本番環境では、WSGIサーバーであるGunicornを使用してアプリケーションを起動し、Apacheをリバースプロキシとして設定することを推奨します。

    a.  **Gunicornのインストールと実行**
        ```bash
        pip install gunicorn
        gunicorn --workers 4 --bind 127.0.0.1:5001 app:app
        ```
        *   `--workers 4`: ワーカープロセスの数。サーバーのCPUコア数に応じて調整してください。
        *   `--bind 127.0.0.1:5001`: GunicornがリッスンするIPアドレスとポート。Apacheからのリクエストを受け付けます。
        *   `app:app`: `app.py` ファイル内のFlaskアプリケーションインスタンス (`app`) を指します。

    b.  **Apache リバースプロキシ設定**
        *   Flaskアプリケーション (`app.py`) には `app.config['APPLICATION_ROOT'] = '/tools/webpolice'` が設定されている必要があります（設定済み）。
        *   Apacheの設定ファイルに以下のリバースプロキシ設定を追記します。 (`mod_proxy` と `mod_proxy_http` モジュールが有効である必要があります)
            ```apache
            <IfModule mod_proxy.c>
                <IfModule mod_proxy_http.c>
                    ProxyRequests Off
                    ProxyPreserveHost On

                    <Location /tools/webpolice/>
                        ProxyPass http://127.0.0.1:5001/tools/webpolice/
                        ProxyPassReverse http://127.0.0.1:5001/tools/webpolice/
                    </Location>
                </IfModule>
            </IfModule>
            ```
        *   Apacheを再起動またはリロードして設定を反映させます。

    c.  **アクセス**
        *   ブラウザで `https://<あなたのドメイン>/tools/webpolice/` を開きます。

## 技術スタック

*   バックエンド: Python (Flask)
*   フロントエンド: HTML, CSS, JavaScript
*   その他ライブラリ: `python-whois`, `requests`, `builtwith`, `beautifulsoup4`, `WeasyPrint`, `dnspython` など (詳細は `requirements.txt` を参照)
