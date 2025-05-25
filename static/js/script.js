document.addEventListener('DOMContentLoaded', () => {
    const urlInput = document.getElementById('urlInput');
    const analyzeButton = document.getElementById('analyzeButton');
    const loadingIndicator = document.getElementById('loadingIndicator');
    const reportSection = document.getElementById('reportSection');
    const reportUrlSpan = document.getElementById('reportUrl');

    const domainInfoPre = document.getElementById('domainInfo');
    const techStackInfoPre = document.getElementById('techStackInfo');
    const existenceDateInfoP = document.getElementById('existenceDateInfo');
    const seoInfoPre = document.getElementById('seoInfo');
    const dnsInfoPre = document.getElementById('dnsInfo');
    const serverInfoPre = document.getElementById('serverInfo');


    const saveReportButton = document.getElementById('saveReportButton');
    const downloadPdfButton = document.getElementById('downloadPdfButton');
    let currentReport = null; // To store the latest report data

    analyzeButton.addEventListener('click', async () => {
        const url = urlInput.value.trim();
        if (!url) {
            alert('URLを入力してください。');
            return;
        }

        loadingIndicator.style.display = 'block';
        reportSection.style.display = 'none';

        try {
            const response = await fetch('/analyze', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ url: url }),
            });

            if (!response.ok) {
                const errorData = await response.json();
                throw new Error(errorData.error || `HTTP error! status: ${response.status}`);
            }

            const report = await response.json();
            currentReport = report; // Store the report data
            displayReport(report);

        } catch (error) {
            console.error('分析エラー:', error);
            alert(`分析中にエラーが発生しました: ${error.message}`);
            domainInfoPre.textContent = `エラー: ${error.message}`;
            techStackInfoPre.textContent = '';
            existenceDateInfoP.textContent = '';
            seoInfoPre.textContent = '';
            dnsInfoPre.textContent = '';
            serverInfoPre.textContent = '';
            // rankingInfoPre and otherInfoPre seem to be remnants or planned features not fully implemented, removing their clearing if they don't exist in HTML
            // rankingInfoPre.textContent = ''; 
            // otherInfoPre.textContent = '';
        } finally {
            loadingIndicator.style.display = 'none';
            reportSection.style.display = 'block';
        }
    });

    function displayReport(report) {
        reportUrlSpan.textContent = report.url;

        // Domain Info
        if (report.domain_info && Object.keys(report.domain_info).length > 0) {
            if(report.domain_info.error){
                domainInfoPre.textContent = report.domain_info.error;
            } else {
                let domainDetails = "";
                for(const key in report.domain_info){
                    if(report.domain_info[key]){
                        domainDetails += `${key}: ${Array.isArray(report.domain_info[key]) ? report.domain_info[key].join(', ') : report.domain_info[key]}\n`;
                    }
                }
                domainInfoPre.textContent = domainDetails || "利用可能な情報はありません。";
            }
        } else {
            domainInfoPre.textContent = "利用可能な情報はありません。";
        }

        // Tech Stack Info - Improved UI
        techStackInfoPre.innerHTML = ''; // Clear previous content
        if (report.tech_stack && Object.keys(report.tech_stack).length > 0) {
            if (report.tech_stack.error) {
                techStackInfoPre.textContent = report.tech_stack.error;
            } else {
                const ul = document.createElement('ul');
                ul.style.listStyleType = 'none';
                ul.style.paddingLeft = '0';

                for (const category in report.tech_stack) {
                    const li = document.createElement('li');
                    const categoryTitle = document.createElement('strong');
                    // Simple mapping for category names, can be expanded
                    let displayName = category.replace(/-/g, ' ').replace(/\b\w/g, l => l.toUpperCase());
                    if (displayName === "Js Frameworks") displayName = "JavaScript フレームワーク";
                    if (displayName === "Web Servers") displayName = "ウェブサーバー";
                    if (displayName === "Programming Languages") displayName = "プログラミング言語";
                    if (displayName === "Cms") displayName = "CMS";
                    if (displayName === "Analytics") displayName = "アクセス解析";
                    if (displayName === "Advertising") displayName = "広告";
                    if (displayName === "Widgets") displayName = "ウィジェット";
                    if (displayName === "Font Scripts") displayName = "フォントスクリプト";
                    if (displayName === "Tag Managers") displayName = "タグマネージャー";
                    if (displayName === "Security") displayName = "セキュリティ";
                    if (displayName === "Cdn") displayName = "CDN";
                     // Add more mappings as needed

                    categoryTitle.textContent = `${displayName}: `;
                    li.appendChild(categoryTitle);

                    const techList = Array.isArray(report.tech_stack[category]) ? report.tech_stack[category].join(', ') : report.tech_stack[category];
                    li.appendChild(document.createTextNode(techList));
                    ul.appendChild(li);
                }
                techStackInfoPre.appendChild(ul);
            }
        } else {
            techStackInfoPre.textContent = "利用可能な技術情報はありません。";
        }
        existenceDateInfoP.textContent = report.existence_date || "分析中または利用可能な情報はありません。";
        // SEO Info - Improved UI
        seoInfoPre.innerHTML = ''; // Clear previous content
        if (report.seo_info && Object.keys(report.seo_info).length > 0) {
            if (report.seo_info.error) {
                seoInfoPre.textContent = report.seo_info.error;
            } else {
                const ul = document.createElement('ul');
                ul.style.listStyleType = 'none';
                ul.style.paddingLeft = '0';

                const items = {
                    'title': 'タイトル',
                    'meta_description': 'メタディスクリプション',
                    'meta_keywords': 'メタキーワード',
                    'h1_tags': 'H1 タグ'
                };

                for (const key in items) {
                    if (report.seo_info.hasOwnProperty(key)) {
                        const li = document.createElement('li');
                        const itemTitle = document.createElement('strong');
                        itemTitle.textContent = `${items[key]}: `;
                        li.appendChild(itemTitle);

                        let value = report.seo_info[key];
                        if (key === 'h1_tags') {
                            value = Array.isArray(value) && value.length > 0 ? value.join(', ') : (value.length === 0 ? "見つかりません" : value);
                        }
                        li.appendChild(document.createTextNode(value || "見つかりません"));
                        ul.appendChild(li);
                    }
                }
                seoInfoPre.appendChild(ul);
            }
        } else {
            seoInfoPre.textContent = "利用可能なSEO情報はありません。";
        }
        
        // DNS Info
        dnsInfoPre.innerHTML = ''; // Clear previous content
        if (report.dns_info && Object.keys(report.dns_info).length > 0) {
            if (report.dns_info.error && !Object.keys(report.dns_info).some(k => k !== 'error' && report.dns_info[k])) {
                dnsInfoPre.textContent = report.dns_info.error;
            } else {
                const ul = document.createElement('ul');
                ul.style.listStyleType = 'none';
                ul.style.paddingLeft = '0';
                if (report.dns_info.error) { // Display general error if present alongside other data
                    const errorLi = document.createElement('li');
                    errorLi.style.color = 'red';
                    errorLi.textContent = `DNS情報取得エラー: ${report.dns_info.error}`;
                    ul.appendChild(errorLi);
                }
                const dnsOrder = ['A', 'AAAA', 'CNAME', 'MX', 'NS', 'TXT', 'SOA'];
                dnsOrder.forEach(recordType => {
                    if (report.dns_info.hasOwnProperty(recordType)) {
                        const li = document.createElement('li');
                        const itemTitle = document.createElement('strong');
                        itemTitle.textContent = `${recordType} レコード: `;
                        li.appendChild(itemTitle);
                        let value = report.dns_info[recordType];
                        if (Array.isArray(value)) {
                            value = value.length > 0 ? value.join('\n') : "見つかりません";
                        } else if (typeof value === 'string' && (value.includes("エラー") || value.includes("タイムアウト") || value.includes("見つかりません") || value.includes("該当レコードなし") || value.includes("ドメインが存在しません"))) {
                            const errorSpan = document.createElement('span');
                            errorSpan.style.color = '#c0392b'; // Error color
                            errorSpan.textContent = value;
                            li.appendChild(errorSpan);
                            ul.appendChild(li);
                            return; // Continue to next record type
                        }
                        li.appendChild(document.createTextNode(value || "N/A"));
                        ul.appendChild(li);
                    }
                });
                dnsInfoPre.appendChild(ul);
            }
        } else {
            dnsInfoPre.textContent = "利用可能なDNS情報はありません。";
        }

        // Server Info
        serverInfoPre.innerHTML = ''; // Clear previous content
        if (report.server_info && Object.keys(report.server_info).length > 0) {
            if (report.server_info.error) {
                serverInfoPre.textContent = report.server_info.error;
            } else {
                const ul = document.createElement('ul');
                ul.style.listStyleType = 'none';
                ul.style.paddingLeft = '0';
                const serverItems = {
                    'ip_address': 'IPアドレス',
                    'country': '国',
                    'isp': 'ISP (プロバイダ)'
                };
                for (const key in serverItems) {
                    if (report.server_info.hasOwnProperty(key)) {
                        const li = document.createElement('li');
                        const itemTitle = document.createElement('strong');
                        itemTitle.textContent = `${serverItems[key]}: `;
                        li.appendChild(itemTitle);
                        li.appendChild(document.createTextNode(report.server_info[key] || "N/A"));
                        ul.appendChild(li);
                    }
                }
                serverInfoPre.appendChild(ul);
            }
        } else {
            serverInfoPre.textContent = "利用可能なサーバー情報はありません。";
        }

        // Show buttons
        saveReportButton.style.display = 'inline-block';
        downloadPdfButton.style.display = 'inline-block';
    }

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
            
            // Extract domain for filename
            let domain = 'report'; // Default
            if (currentReport.url) {
                try {
                    const urlObject = new URL(currentReport.url);
                    domain = urlObject.hostname.replace(/www\./i, ''); // Remove www.
                } catch (e) {
                    console.warn('Could not parse URL for filename:', currentReport.url);
                }
            }
            const dateStr = new Date().toISOString().slice(0, 10);
            a.download = `website-analysis-${domain}-${dateStr}.json`;
            
            a.href = url;
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            URL.revokeObjectURL(url);
            console.log('Report saved as JSON.');
        } catch (error) {
            console.error('レポートの保存中にエラー:', error);
            alert('レポートのJSONファイルへの保存中にエラーが発生しました。');
        }
    });

    downloadPdfButton.addEventListener('click', async () => {
        if (!currentReport) {
            alert('PDFを生成するためのレポートデータがありません。まずURLを分析してください。');
            return;
        }

        loadingIndicator.style.display = 'block'; // Show loading indicator

        try {
            const response = await fetch('/download_pdf', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify(currentReport),
            });

            if (!response.ok) {
                let errorMsg = `PDF生成エラー: ${response.status}`;
                try {
                    const errorData = await response.json();
                    errorMsg = errorData.error || errorMsg;
                } catch (e) {
                    // If response is not JSON, use the status text
                    errorMsg = `PDF生成エラー: ${response.status} ${response.statusText}`;
                }
                throw new Error(errorMsg);
            }

            const blob = await response.blob();
            const url = URL.createObjectURL(blob);
            const a = document.createElement('a');
            
            // Try to get filename from Content-Disposition header
            const disposition = response.headers.get('Content-Disposition');
            let filename = 'website-analysis-report.pdf'; // Default filename
            if (disposition && disposition.indexOf('attachment') !== -1) {
                const filenameRegex = /filename[^;=\n]*=((['"]).*?\2|[^;\n]*)/;
                const matches = filenameRegex.exec(disposition);
                if (matches != null && matches[1]) {
                    filename = matches[1].replace(/['"]/g, '');
                }
            }
            a.download = filename;
            a.href = url;
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            URL.revokeObjectURL(url);
            console.log('Report downloaded as PDF.');

        } catch (error) {
            console.error('PDFダウンロードエラー:', error);
            alert(`PDFのダウンロード中にエラーが発生しました: ${error.message}`);
        } finally {
            loadingIndicator.style.display = 'none'; // Hide loading indicator
        }
    });
});
