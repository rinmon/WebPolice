<?php
// アプリケーションバージョン
define('APP_VERSION', '1.1.0');

// リクエストが Ajax からのものか判断
$isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
          strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';

// Ajax リクエストの処理
if ($isAjax && $_SERVER['REQUEST_METHOD'] === 'POST') {
    // 入力データの取得とサニタイズ
    $postData = json_decode(file_get_contents('php://input'), true);
    
    if (empty($postData) || empty($postData['action'])) {
        echo json_encode(['error' => '無効なリクエストです']);
        exit;
    }
    
    // 必要なファイルをインクルード
    require_once 'functions.php';
    
    $action = $postData['action'];
    $response = [];
    
    switch ($action) {
        case 'analyze':
            if (empty($postData['url'])) {
                $response = ['error' => 'URLが必要です'];
                break;
            }
            
            $url = filter_var($postData['url'], FILTER_SANITIZE_URL);
            
            // URLが有効でない場合はプロトコルを追加
            if (!filter_var($url, FILTER_VALIDATE_URL)) {
                if (strpos($url, 'http') !== 0) {
                    $url = 'http://' . $url;
                }
            }
            
            // ドメイン取得
            $parsedUrl = parse_url($url);
            $domain = isset($parsedUrl['host']) ? $parsedUrl['host'] : '';
            
            if (empty($domain)) {
                $response = ['error' => '有効なURLではありません'];
                break;
            }
            
            // 分析の実行
            $response = [
                'url' => $url,
                'domain' => $domain,
                'domain_info' => getDomainInfo($domain),
                'tech_stack' => getTechStack($url),
                'existence_date' => getExistenceDate($domain),
                'seo_info' => getSeoInfo($url),
                'dns_info' => getDnsInfo($domain),
                'server_info' => getServerInfo($domain)
            ];
            break;
            
        case 'generate_pdf':
            if (empty($postData['report'])) {
                $response = ['error' => 'レポートデータが必要です'];
                break;
            }
            
            $reportData = $postData['report'];
            $pdfPath = generatePDF($reportData);
            
            if ($pdfPath) {
                $response = ['success' => true, 'pdf_path' => $pdfPath];
            } else {
                $response = ['error' => 'PDFの生成に失敗しました'];
            }
            break;
            
        default:
            $response = ['error' => '不明なアクション'];
    }
    
    // JSONレスポンス
    header('Content-Type: application/json');
    echo json_encode($response);
    exit;
}

// PDFダウンロードリクエストの処理
if (isset($_GET['download_pdf']) && !empty($_GET['file'])) {
    $file = basename($_GET['file']);
    $filePath = 'tmp/' . $file;
    
    if (file_exists($filePath) && pathinfo($filePath, PATHINFO_EXTENSION) === 'pdf') {
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="' . $file . '"');
        header('Content-Length: ' . filesize($filePath));
        readfile($filePath);
        exit;
    }
}

// メインページの表示
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ウェブサイト技術分析レポート</title>
    <style>
        body {
            font-family: 'Helvetica Neue', Arial, 'Hiragino Kaku Gothic ProN', 'Hiragino Sans', Meiryo, sans-serif;
            line-height: 1.6;
            color: #333;
            margin: 0;
            padding: 0;
            background-color: #f5f5f5;
        }
        .container {
            max-width: 1000px;
            margin: 0 auto;
            padding: 20px;
            background-color: #fff;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
        }
        header {
            text-align: center;
            margin-bottom: 30px;
        }
        h1 {
            color: #2c3e50;
            border-bottom: 2px solid #3498db;
            padding-bottom: 10px;
        }
        h2 {
            color: #3498db;
            margin-top: 30px;
            border-bottom: 1px solid #bdc3c7;
            padding-bottom: 8px;
        }
        h3 {
            color: #2980b9;
            margin-top: 20px;
        }
        .input-section {
            display: flex;
            margin-bottom: 30px;
        }
        #urlInput {
            flex: 1;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px 0 0 4px;
            font-size: 16px;
        }
        #analyzeButton {
            padding: 10px 20px;
            background-color: #3498db;
            color: white;
            border: none;
            border-radius: 0 4px 4px 0;
            cursor: pointer;
            font-size: 16px;
        }
        #analyzeButton:hover {
            background-color: #2980b9;
        }
        #loadingIndicator {
            text-align: center;
            margin: 20px 0;
            font-weight: bold;
        }
        .report-category {
            background-color: #f9f9f9;
            border: 1px solid #ecf0f1;
            border-radius: 5px;
            padding: 15px;
            margin-bottom: 20px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
            table-layout: fixed;
        }
        th, td {
            border: 1px solid #bdc3c7;
            padding: 8px;
            text-align: left;
            vertical-align: top;
            word-wrap: break-word;
        }
        th {
            background-color: #ecf0f1;
            font-weight: bold;
            color: #2c3e50;
            width: 30%;
        }
        td {
            width: 70%;
        }
        .error {
            color: #e74c3c;
            font-weight: bold;
            background-color: #fdd;
            padding: 10px;
            border-radius: 3px;
        }
        button {
            padding: 10px 15px;
            background-color: #3498db;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            margin-right: 10px;
            margin-top: 20px;
        }
        button:hover {
            background-color: #2980b9;
        }
        footer {
            margin-top: 50px;
            text-align: center;
            color: #7f8c8d;
            font-size: 14px;
        }
        ul {
            margin: 0;
            padding-left: 20px;
        }
    </style>
</head>
<body>
    <div class="container">
        <header>
            <h1>ウェブサイト技術分析レポート</h1>
            <div style="display: flex; justify-content: center; margin-bottom: 15px;">
                <img src="https://img.shields.io/badge/version-1.1.0-blue" alt="Version" style="margin-right: 10px;"/>
                <a href="https://github.com/rinmon/webpolice" target="_blank">
                    <img src="https://img.shields.io/github/stars/rinmon/webpolice?style=social" alt="GitHub Stars"/>
                </a>
            </div>
        </header>

        <main>
            <section class="input-section">
                <input type="text" id="urlInput" placeholder="分析したいウェブサイトのURLを入力 (例: example.com)">
                <button id="analyzeButton">分析開始</button>
            </section>

            <section id="loadingIndicator" style="display: none;">
                <p>分析中...</p>
            </section>

            <section id="reportSection" style="display: none;">
                <h2>分析レポート: <span id="reportUrl"></span></h2>
                
                <div class="report-category" id="domainInfoCategory">
                    <h3>ドメイン情報 (WHOIS)</h3>
                    <div id="domainInfo"></div>
                </div>

                <div class="report-category" id="techStackCategory">
                    <h3>使用技術</h3>
                    <div id="techStackInfo"></div>
                </div>

                <div class="report-category" id="existenceDateCategory">
                    <h3>サイト存在開始時期 (推定)</h3>
                    <p id="existenceDateInfo"></p>
                </div>
                
                <div class="report-category" id="seoInfoCategory">
                    <h3>SEO情報</h3>
                    <div id="seoInfo"></div>
                </div>

                <div class="report-category" id="dnsInfoCategory">
                    <h3>DNS情報</h3>
                    <div id="dnsInfo"></div>
                </div>

                <div class="report-category" id="serverInfoCategory">
                    <h3>サーバー情報</h3>
                    <div id="serverInfo"></div>
                </div>

                <button id="saveReportButton">レポートを保存</button>
                <button id="downloadPdfButton">PDFでダウンロード</button>
            </section>
        </main>

        <footer>
            <p>&copy; 2025 ウェブサイト技術分析ツール - Version: <?php echo APP_VERSION; ?></p>
        </footer>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            // DOM要素の取得
            const urlInput = document.getElementById('urlInput');
            const analyzeButton = document.getElementById('analyzeButton');
            const loadingIndicator = document.getElementById('loadingIndicator');
            const reportSection = document.getElementById('reportSection');
            const reportUrlSpan = document.getElementById('reportUrl');
            const saveReportButton = document.getElementById('saveReportButton');
            const downloadPdfButton = document.getElementById('downloadPdfButton');
            
            // レポート表示用要素
            const domainInfoDiv = document.getElementById('domainInfo');
            const techStackInfoDiv = document.getElementById('techStackInfo');
            const existenceDateInfoP = document.getElementById('existenceDateInfo');
            const seoInfoDiv = document.getElementById('seoInfo');
            const dnsInfoDiv = document.getElementById('dnsInfo');
            const serverInfoDiv = document.getElementById('serverInfo');
            
            let currentReport = null; // 最新のレポートデータを保存
            
            // 分析ボタンのイベントリスナー
            analyzeButton.addEventListener('click', async () => {
                const url = urlInput.value.trim();
                if (!url) {
                    alert('URLを入力してください。');
                    return;
                }
                
                // 分析開始
                loadingIndicator.style.display = 'block';
                reportSection.style.display = 'none';
                
                try {
                    const response = await fetch('index.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest'
                        },
                        body: JSON.stringify({ 
                            action: 'analyze',
                            url: url 
                        })
                    });
                    
                    if (!response.ok) {
                        throw new Error(`HTTP error! status: ${response.status}`);
                    }
                    
                    const report = await response.json();
                    
                    if (report.error) {
                        throw new Error(report.error);
                    }
                    
                    currentReport = report;
                    displayReport(report);
                    
                } catch (error) {
                    console.error('分析エラー:', error);
                    alert(`分析中にエラーが発生しました: ${error.message}`);
                } finally {
                    loadingIndicator.style.display = 'none';
                    reportSection.style.display = 'block';
                }
            });
            
            // レポート表示関数
            function displayReport(report) {
                reportUrlSpan.textContent = report.url;
                
                // ドメイン情報
                displayDomainInfo(report.domain_info);
                
                // 技術スタック
                displayTechStack(report.tech_stack);
                
                // サイト存在開始時期
                existenceDateInfoP.textContent = report.existence_date || "情報なし";
                
                // SEO情報
                displaySeoInfo(report.seo_info);
                
                // DNS情報
                displayDnsInfo(report.dns_info);
                
                // サーバー情報
                displayServerInfo(report.server_info);
                
                // ボタンを表示
                saveReportButton.style.display = 'inline-block';
                downloadPdfButton.style.display = 'inline-block';
            }
            
            // ドメイン情報表示関数
            function displayDomainInfo(domainInfo) {
                domainInfoDiv.innerHTML = '';
                
                if (!domainInfo || Object.keys(domainInfo).length === 0) {
                    domainInfoDiv.textContent = "利用可能な情報はありません。";
                    return;
                }
                
                if (domainInfo.error) {
                    domainInfoDiv.innerHTML = `<p class="error">${domainInfo.error}</p>`;
                    return;
                }
                
                const table = document.createElement('table');
                
                for (const key in domainInfo) {
                    if (key.toLowerCase() === 'error') continue;
                    
                    const row = table.insertRow();
                    const td = row.insertCell();
                    const th = document.createElement('th');
                    
                    const value = domainInfo[key];
                    td.textContent = Array.isArray(value) ? value.join(', ') : value;
                    
                    th.textContent = key.replace('_', ' ').replace(/\b\w/g, c => c.toUpperCase());
                    row.insertCell(0).appendChild(th);
                }
                
                domainInfoDiv.appendChild(table);
            }
            
            // 技術スタック表示関数
            function displayTechStack(techStack) {
                // 上記と同様の実装...省略
                techStackInfoDiv.innerHTML = '';
                
                if (!techStack || Object.keys(techStack).length === 0) {
                    techStackInfoDiv.textContent = "利用可能な情報はありません。";
                    return;
                }
                
                if (techStack.error) {
                    techStackInfoDiv.innerHTML = `<p class="error">${techStack.error}</p>`;
                    return;
                }
                
                const table = document.createElement('table');
                
                for (const category in techStack) {
                    if (category.toLowerCase() === 'error') continue;
                    
                    const techs = techStack[category];
                    
                    if (!techs || (Array.isArray(techs) && techs.length === 0)) continue;
                    
                    const row = table.insertRow();
                    const td = row.insertCell();
                    const th = document.createElement('th');
                    
                    td.textContent = Array.isArray(techs) ? techs.join(', ') : techs;
                    th.textContent = category;
                    row.insertCell(0).appendChild(th);
                }
                
                techStackInfoDiv.appendChild(table);
            }
            
            // SEO情報表示関数
            function displaySeoInfo(seoInfo) {
                // 省略...他の表示関数と同様の実装
                seoInfoDiv.innerHTML = '';
                
                if (!seoInfo || Object.keys(seoInfo).length === 0) {
                    seoInfoDiv.textContent = "利用可能な情報はありません。";
                    return;
                }
                
                if (seoInfo.error) {
                    seoInfoDiv.innerHTML = `<p class="error">${seoInfo.error}</p>`;
                    return;
                }
                
                const table = document.createElement('table');
                
                for (const key in seoInfo) {
                    if (key.toLowerCase() === 'error') continue;
                    
                    const row = table.insertRow();
                    const td = row.insertCell();
                    const th = document.createElement('th');
                    
                    const value = seoInfo[key];
                    
                    if (key === 'h1_tags' && Array.isArray(value)) {
                        td.textContent = value.length > 0 ? value.join(', ') : '見つかりません';
                    } else {
                        td.textContent = value || '見つかりません';
                    }
                    
                    th.textContent = key === 'title' ? 'タイトル' : 
                                    key === 'meta_description' ? 'メタディスクリプション' :
                                    key === 'meta_keywords' ? 'メタキーワード' : 
                                    key === 'h1_tags' ? 'H1 タグ' : key;
                    row.insertCell(0).appendChild(th);
                }
                
                seoInfoDiv.appendChild(table);
            }
            
            // DNS情報表示関数
            function displayDnsInfo(dnsInfo) {
                // 省略...
                dnsInfoDiv.innerHTML = '';
                
                if (!dnsInfo || Object.keys(dnsInfo).length === 0) {
                    dnsInfoDiv.textContent = "利用可能な情報はありません。";
                    return;
                }
                
                if (dnsInfo.error && Object.keys(dnsInfo).length === 1) {
                    dnsInfoDiv.innerHTML = `<p class="error">${dnsInfo.error}</p>`;
                    return;
                }
                
                const table = document.createElement('table');
                
                if (dnsInfo.error) {
                    const row = table.insertRow();
                    const cell = row.insertCell();
                    cell.colSpan = 2;
                    cell.innerHTML = `<p class="error">DNS情報取得エラー: ${dnsInfo.error}</p>`;
                }
                
                const dnsOrder = ['A', 'AAAA', 'CNAME', 'MX', 'NS', 'TXT'];
                
                for (const recordType of dnsOrder) {
                    if (!dnsInfo.hasOwnProperty(recordType)) continue;
                    
                    const row = table.insertRow();
                    const td = row.insertCell();
                    const th = document.createElement('th');
                    
                    const value = dnsInfo[recordType];
                    
                    if (typeof value === 'string' && (value.includes('エラー') || value.includes('タイムアウト') || value.includes('見つかりません') || value.includes('該当レコードなし'))) {
                        td.innerHTML = `<span class="error-inline" style="color: #c0392b;">${value}</span>`;
                    } else if (Array.isArray(value)) {
                        if (value.length === 0 || value[0] === '見つかりません' || value[0] === '該当レコードなし') {
                            td.textContent = value[0] || 'N/A';
                        } else {
                            const ul = document.createElement('ul');
                            value.forEach(v => {
                                const li = document.createElement('li');
                                li.textContent = v;
                                ul.appendChild(li);
                            });
                            td.appendChild(ul);
                        }
                    } else {
                        td.textContent = value || 'N/A';
                    }
                    
                    th.textContent = `${recordType} レコード`;
                    row.insertCell(0).appendChild(th);
                }
                
                dnsInfoDiv.appendChild(table);
            }
            
            // サーバー情報表示関数
            function displayServerInfo(serverInfo) {
                // 省略...
                serverInfoDiv.innerHTML = '';
                
                if (!serverInfo || Object.keys(serverInfo).length === 0) {
                    serverInfoDiv.textContent = "利用可能な情報はありません。";
                    return;
                }
                
                if (serverInfo.error) {
                    serverInfoDiv.innerHTML = `<p class="error">${serverInfo.error}</p>`;
                    return;
                }
                
                const table = document.createElement('table');
                
                const serverMap = {
                    'ip_address': 'IPアドレス',
                    'country': '国',
                    'isp': 'ISP (プロバイダ)'
                };
                
                for (const key in serverMap) {
                    if (!serverInfo.hasOwnProperty(key)) continue;
                    
                    const row = table.insertRow();
                    const td = row.insertCell();
                    const th = document.createElement('th');
                    
                    td.textContent = serverInfo[key] || 'N/A';
                    th.textContent = serverMap[key];
                    row.insertCell(0).appendChild(th);
                }
                
                serverInfoDiv.appendChild(table);
            }
            
            // レポート保存ボタンのイベントリスナー
            saveReportButton.addEventListener('click', () => {
                if (!currentReport) {
                    alert('保存するレポートデータがありません。まずURLを分析してください。');
                    return;
                }
                
                try {
                    const jsonData = JSON.stringify(currentReport, null, 2);
                    const blob = new Blob([jsonData], { type: 'application/json' });
                    const url = URL.createObjectURL(blob);
                    const a = document.createElement('a');
                    
                    // ファイル名用のドメイン抽出
                    let domain = 'report';
                    if (currentReport.url) {
                        try {
                            const urlObject = new URL(currentReport.url);
                            domain = urlObject.hostname.replace(/www\./i, '');
                        } catch (e) {
                            console.warn('ファイル名用のURL解析エラー:', currentReport.url);
                        }
                    }
                    
                    const dateStr = new Date().toISOString().slice(0, 10);
                    a.download = `website-analysis-${domain}-${dateStr}.json`;
                    
                    a.href = url;
                    document.body.appendChild(a);
                    a.click();
                    document.body.removeChild(a);
                    URL.revokeObjectURL(url);
                    
                    console.log('レポートをJSON形式で保存しました。');
                } catch (error) {
                    console.error('レポート保存エラー:', error);
                    alert('レポートのJSONファイルへの保存中にエラーが発生しました。');
                }
            });
            
            // PDF生成ボタンのイベントリスナー
            downloadPdfButton.addEventListener('click', async () => {
                if (!currentReport) {
                    alert('PDFを生成するためのレポートデータがありません。まずURLを分析してください。');
                    return;
                }
                
                loadingIndicator.style.display = 'block';
                
                try {
                    const response = await fetch('index.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest'
                        },
                        body: JSON.stringify({ 
                            action: 'generate_pdf',
                            report: currentReport
                        })
                    });
                    
                    if (!response.ok) {
                        throw new Error(`HTTP error! status: ${response.status}`);
                    }
                    
                    const data = await response.json();
                    
                    if (data.error) {
                        throw new Error(data.error);
                    }
                    
                    if (data.pdf_path) {
                        window.location.href = `index.php?download_pdf=1&file=${data.pdf_path.split('/').pop()}`;
                    }
                    
                } catch (error) {
                    console.error('PDF生成エラー:', error);
                    alert(`PDFの生成中にエラーが発生しました: ${error.message}`);
                } finally {
                    loadingIndicator.style.display = 'none';
                }
            });
        });
    </script>
</body>
</html>
