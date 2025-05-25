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

4.  **Flaskアプリケーションを実行します**
    ```bash
    python app.py
    ```

5.  ブラウザで `http://127.0.0.1:5001/` を開きます。(ポートは `app.py` で変更可能です)

## 技術スタック

*   バックエンド: Python (Flask)
*   フロントエンド: HTML, CSS, JavaScript
*   その他ライブラリ: `python-whois`, `requests`, `builtwith`, `beautifulsoup4`, `WeasyPrint`, `dnspython` など (詳細は `requirements.txt` を参照)
