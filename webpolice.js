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
    
    // CORSプロキシの設定
    const corsProxy = 'https://api.allorigins.win/raw?url=';
    
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
            // URLの正規化
            let normalizedUrl = url;
            if (!normalizedUrl.startsWith('http://') && !normalizedUrl.startsWith('https://')) {
                normalizedUrl = 'http://' + normalizedUrl;
            }
            
            // ドメイン取得
            const domain = new URL(normalizedUrl).hostname;
            
            // レポートオブジェクト初期化
            const report = {
                url: normalizedUrl,
                domain: domain,
                domain_info: {},
                tech_stack: {},
                existence_date: null,
                seo_info: {},
                dns_info: {},
                server_info: {}
            };
            
            // 並列で各APIリクエストを実行
            await Promise.allSettled([
                fetchWhoisInfo(domain, report),
                fetchTechStack(normalizedUrl, report),
                fetchWaybackInfo(domain, report),
                fetchSeoInfo(normalizedUrl, report),
                fetchDnsInfo(domain, report),
                fetchServerInfo(domain, report)
            ]);
            
            // レポートを保存
            currentReport = report;
            
            // レポート表示
            displayReport(report);
            
        } catch (error) {
            console.error('分析エラー:', error);
            alert(`分析中にエラーが発生しました: ${error.message}`);
        } finally {
            loadingIndicator.style.display = 'none';
            reportSection.style.display = 'block';
        }
    });
    
    // WHOIS情報取得関数
    async function fetchWhoisInfo(domain, report) {
        try {
            const response = await fetch('/api/whois', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ domain }),
            });
            
            if (!response.ok) {
                const errorData = await response.json();
                throw new Error(errorData.error || `WHOIS API error: ${response.status}`);
            }
            
            const data = await response.json();
            report.domain_info = data;
        } catch (error) {
            console.error('WHOIS情報取得エラー:', error);
            report.domain_info = { error: `WHOIS情報の取得に失敗しました: ${error.message}` };
        }
    }
    
    // 技術スタック取得関数
    async function fetchTechStack(url, report) {
        try {
            const response = await fetch('/api/tech-stack', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ url }),
            });
            
            if (!response.ok) {
                const errorData = await response.json();
                throw new Error(errorData.error || `Tech Stack API error: ${response.status}`);
            }
            
            const data = await response.json();
            report.tech_stack = data;
        } catch (error) {
            console.error('技術情報取得エラー:', error);
            report.tech_stack = { error: `技術情報の分析に失敗しました: ${error.message}` };
        }
    }
    
    // Wayback Machine情報取得関数
    async function fetchWaybackInfo(domain, report) {
        try {
            const response = await fetch('/api/wayback', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ domain }),
            });
            
            if (!response.ok) {
                const errorData = await response.json();
                throw new Error(errorData.error || `Wayback API error: ${response.status}`);
            }
            
            const data = await response.json();
            report.existence_date = data.existence_date;
        } catch (error) {
            console.error('Wayback情報取得エラー:', error);
            report.existence_date = `サイト存在開始時期の取得中にエラー: ${error.message}`;
        }
    }
    
    // SEO情報取得関数
    async function fetchSeoInfo(url, report) {
        try {
            const response = await fetch('/api/seo', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ url }),
            });
            
            if (!response.ok) {
                const errorData = await response.json();
                throw new Error(errorData.error || `SEO API error: ${response.status}`);
            }
            
            const data = await response.json();
            report.seo_info = data;
        } catch (error) {
            console.error('SEO情報取得エラー:', error);
            report.seo_info = { error: `SEO情報の解析中にエラーが発生しました: ${error.message}` };
        }
    }
    
    // DNS情報取得関数
    async function fetchDnsInfo(domain, report) {
        try {
            const response = await fetch('/api/dns', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ domain }),
            });
            
            if (!response.ok) {
                const errorData = await response.json();
                throw new Error(errorData.error || `DNS API error: ${response.status}`);
            }
            
            const data = await response.json();
            report.dns_info = data;
        } catch (error) {
            console.error('DNS情報取得エラー:', error);
            report.dns_info = { error: `DNS情報の取得処理中にエラー: ${error.message}` };
        }
    }
    
    // サーバー情報取得関数
    async function fetchServerInfo(domain, report) {
        try {
            const response = await fetch('/api/server-info', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ domain }),
            });
            
            if (!response.ok) {
                const errorData = await response.json();
                throw new Error(errorData.error || `Server Info API error: ${response.status}`);
            }
            
            const data = await response.json();
            report.server_info = data;
        } catch (error) {
            console.error('サーバー情報取得エラー:', error);
            report.server_info = { error: `サーバー情報の取得処理中にエラー: ${error.message}` };
        }
    }
    
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
            
            td.textContent = Array.isArray(domainInfo[key]) ? domainInfo[key].join(', ') : domainInfo[key];
            th.textContent = key.replace('_', ' ').replace(/\b\w/g, c => c.toUpperCase());
            row.insertCell(0).appendChild(th);
        }
        
        domainInfoDiv.appendChild(table);
    }
    
    // 技術スタック表示関数
    function displayTechStack(techStack) {
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
            
            // カテゴリ名の日本語マッピング
            const categoryMap = {
                'JavaScript Frameworks': 'JavaScriptフレームワーク',
                'Web Servers': 'ウェブサーバー',
                'Programming Languages': 'プログラミング言語',
                'CMS': 'CMS',
                'Analytics': 'アクセス解析',
                'Advertising': '広告',
                'Widgets': 'ウィジェット'
            };
            
            td.textContent = Array.isArray(techs) ? techs.join(', ') : techs;
            th.textContent = categoryMap[category] || category;
            row.insertCell(0).appendChild(th);
        }
        
        techStackInfoDiv.appendChild(table);
    }
    
    // SEO情報表示関数
    function displaySeoInfo(seoInfo) {
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
        
        const seoMap = {
            'title': 'タイトル',
            'meta_description': 'メタディスクリプション',
            'meta_keywords': 'メタキーワード',
            'h1_tags': 'H1 タグ'
        };
        
        for (const key in seoMap) {
            if (!seoInfo.hasOwnProperty(key)) continue;
            
            const row = table.insertRow();
            const td = row.insertCell();
            const th = document.createElement('th');
            
            const value = seoInfo[key];
            
            if (key === 'h1_tags' && Array.isArray(value)) {
                td.textContent = value.length > 0 ? value.join(', ') : '見つかりません';
            } else {
                td.textContent = value || '見つかりません';
            }
            
            th.textContent = seoMap[key];
            row.insertCell(0).appendChild(th);
        }
        
        seoInfoDiv.appendChild(table);
    }
    
    // DNS情報表示関数
    function displayDnsInfo(dnsInfo) {
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
            // レポートセクションのスタイルを一時的に調整
            const originalOverflow = reportSection.style.overflow;
            reportSection.style.overflow = 'visible';
            
            // HTML2Canvasでレポートセクションをキャプチャ
            const canvas = await html2canvas(reportSection, {
                scale: 2,
                logging: false,
                useCORS: true,
                allowTaint: true
            });
            
            // スタイルを元に戻す
            reportSection.style.overflow = originalOverflow;
            
            // jsPDFでPDFを生成
            const { jsPDF } = window.jspdf;
            const pdf = new jsPDF('p', 'mm', 'a4');
            
            // キャンバスをPDFに追加
            const imgData = canvas.toDataURL('image/jpeg', 1.0);
            const pageWidth = pdf.internal.pageSize.getWidth();
            const pageHeight = pdf.internal.pageSize.getHeight();
            const imgWidth = canvas.width;
            const imgHeight = canvas.height;
            const ratio = Math.min(pageWidth / imgWidth, pageHeight / imgHeight);
            const imgX = (pageWidth - imgWidth * ratio) / 2;
            const imgY = 30;
            
            // PDFにタイトルを追加
            pdf.setFontSize(16);
            pdf.text('ウェブサイト技術分析レポート', pageWidth / 2, 20, { align: 'center' });
            
            // 画像を追加
            pdf.addImage(imgData, 'JPEG', imgX, imgY, imgWidth * ratio, imgHeight * ratio);
            
            // PDFをダウンロード
            let domain = 'report';
            if (currentReport.url) {
                try {
                    domain = new URL(currentReport.url).hostname.replace(/www\./i, '');
                } catch (e) {}
            }
            
            const dateStr = new Date().toISOString().slice(0, 10);
            pdf.save(`website-analysis-${domain}-${dateStr}.pdf`);
            
            console.log('レポートをPDF形式で保存しました。');
        } catch (error) {
            console.error('PDF生成エラー:', error);
            alert(`PDFの生成中にエラーが発生しました: ${error.message}`);
        } finally {
            loadingIndicator.style.display = 'none';
        }
    });
});
