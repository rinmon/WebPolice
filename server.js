const express = require('express');
const axios = require('axios');
const whoisJson = require('whois-json');
const dns = require('dns').promises;
const path = require('path');
const { exec } = require('child_process');
const util = require('util');
const execPromise = util.promisify(exec);

const app = express();
const PORT = process.env.PORT || 8080; // Lightsailではデフォルトで8080ポートを使用することが多いです

app.use(express.static(path.join(__dirname)));
app.use(express.json());

// WHOIS情報を取得するエンドポイント
app.post('/api/whois', async (req, res) => {
    try {
        const { domain } = req.body;
        if (!domain) {
            return res.status(400).json({ error: 'ドメインが必要です' });
        }
        
        const whoisData = await whoisJson(domain);
        return res.json(whoisData);
    } catch (error) {
        console.error('WHOIS Error:', error);
        return res.status(500).json({ error: `WHOIS情報の取得に失敗しました: ${error.message}` });
    }
});

// サイト技術情報を取得するエンドポイント
app.post('/api/tech-stack', async (req, res) => {
    try {
        const { url } = req.body;
        if (!url) {
            return res.status(400).json({ error: 'URLが必要です' });
        }
        
        // ウェブサイトのHTMLを取得
        const response = await axios.get(url, {
            headers: {
                'User-Agent': 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36'
            }
        });
        
        const html = response.data;
        
        // 基本的な技術検出（より精密な実装は可能）
        const techStack = {
            'JavaScript Frameworks': [],
            'Web Servers': [],
            'Programming Languages': ['HTML'],
            'CMS': []
        };
        
        // JavaScriptフレームワークの検出
        if (html.includes('react')) techStack['JavaScript Frameworks'].push('React');
        if (html.includes('vue')) techStack['JavaScript Frameworks'].push('Vue.js');
        if (html.includes('angular')) techStack['JavaScript Frameworks'].push('Angular');
        if (html.includes('jquery')) techStack['JavaScript Frameworks'].push('jQuery');
        
        // プログラミング言語の検出
        if (html.includes('<script')) techStack['Programming Languages'].push('JavaScript');
        if (html.includes('.php') || html.includes('WordPress')) techStack['Programming Languages'].push('PHP');
        if (html.includes('.py') || html.includes('Django') || html.includes('Flask')) techStack['Programming Languages'].push('Python');
        
        // CMSの検出
        if (html.includes('WordPress')) techStack['CMS'].push('WordPress');
        if (html.includes('Drupal')) techStack['CMS'].push('Drupal');
        if (html.includes('Joomla')) techStack['CMS'].push('Joomla');
        
        // サーバー情報の検出（ヘッダーから）
        const server = response.headers['server'];
        if (server) {
            if (server.includes('Apache')) techStack['Web Servers'].push('Apache');
            if (server.includes('nginx')) techStack['Web Servers'].push('Nginx');
            if (server.includes('IIS')) techStack['Web Servers'].push('IIS');
        }
        
        return res.json(techStack);
    } catch (error) {
        console.error('Tech Stack Error:', error);
        return res.status(500).json({ error: `技術情報の取得に失敗しました: ${error.message}` });
    }
});

// Wayback Machine情報を取得するエンドポイント
app.post('/api/wayback', async (req, res) => {
    try {
        const { domain } = req.body;
        if (!domain) {
            return res.status(400).json({ error: 'ドメインが必要です' });
        }
        
        const response = await axios.get(`https://archive.org/wayback/available?url=${domain}`);
        
        if (response.data.archived_snapshots && response.data.archived_snapshots.closest) {
            const timestamp = response.data.archived_snapshots.closest.timestamp;
            const year = timestamp.substring(0, 4);
            const month = timestamp.substring(4, 6);
            const day = timestamp.substring(6, 8);
            return res.json({ existence_date: `${year}年${month}月${day}日頃` });
        } else {
            return res.json({ existence_date: "Wayback Machineに記録が見つかりませんでした。" });
        }
    } catch (error) {
        console.error('Wayback Error:', error);
        return res.status(500).json({ error: `サイト存在開始時期の取得中にエラー: ${error.message}` });
    }
});

// SEO情報を取得するエンドポイント
app.post('/api/seo', async (req, res) => {
    try {
        const { url } = req.body;
        if (!url) {
            return res.status(400).json({ error: 'URLが必要です' });
        }
        
        const response = await axios.get(url, {
            headers: {
                'User-Agent': 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36'
            }
        });
        
        const html = response.data;
        const cheerio = require('cheerio');
        const $ = cheerio.load(html);
        
        const seoData = {
            title: $('title').text().trim() || "見つかりません",
            meta_description: $('meta[name="description"]').attr('content') || "見つかりません",
            meta_keywords: $('meta[name="keywords"]').attr('content') || "見つかりません",
            h1_tags: []
        };
        
        $('h1').each((index, element) => {
            seoData.h1_tags.push($(element).text().trim());
        });
        
        return res.json(seoData);
    } catch (error) {
        console.error('SEO Error:', error);
        return res.status(500).json({ error: `SEO情報の解析中にエラーが発生しました: ${error.message}` });
    }
});

// DNS情報を取得するエンドポイント
app.post('/api/dns', async (req, res) => {
    try {
        const { domain } = req.body;
        if (!domain) {
            return res.status(400).json({ error: 'ドメインが必要です' });
        }
        
        const dnsRecords = {};
        const recordTypes = ['A', 'AAAA', 'MX', 'NS', 'CNAME', 'TXT'];
        
        for (const recordType of recordTypes) {
            try {
                let records;
                switch (recordType) {
                    case 'A':
                        records = await dns.resolve4(domain);
                        break;
                    case 'AAAA':
                        records = await dns.resolve6(domain);
                        break;
                    case 'MX':
                        records = await dns.resolveMx(domain);
                        records = records.map(r => `${r.priority} ${r.exchange}`);
                        break;
                    case 'NS':
                        records = await dns.resolveNs(domain);
                        break;
                    case 'CNAME':
                        records = await dns.resolveCname(domain);
                        break;
                    case 'TXT':
                        records = await dns.resolveTxt(domain);
                        records = records.map(r => r.join(' '));
                        break;
                }
                
                dnsRecords[recordType] = records && records.length > 0 ? records : ["該当レコードなし"];
            } catch (error) {
                if (error.code === 'ENOTFOUND' || error.code === 'ENODATA') {
                    dnsRecords[recordType] = ["該当レコードなし"];
                } else {
                    dnsRecords[recordType] = [`エラー (${recordType}): ${error.code || error.message}`];
                }
            }
        }
        
        return res.json(dnsRecords);
    } catch (error) {
        console.error('DNS Error:', error);
        return res.status(500).json({ error: `DNS情報の取得処理中にエラー: ${error.message}` });
    }
});

// サーバー情報を取得するエンドポイント
app.post('/api/server-info', async (req, res) => {
    try {
        const { domain } = req.body;
        if (!domain) {
            return res.status(400).json({ error: 'ドメインが必要です' });
        }
        
        // IPアドレスを取得
        const addresses = await dns.resolve4(domain);
        if (!addresses || addresses.length === 0) {
            return res.status(404).json({ error: 'IPアドレスが見つかりませんでした' });
        }
        
        const ipAddress = addresses[0];
        
        // IPアドレスからの地理情報を取得
        const ipResponse = await axios.get(`https://ipapi.co/${ipAddress}/json/`);
        
        const serverInfo = {
            ip_address: ipAddress,
            country: `${ipResponse.data.country_name} (${ipResponse.data.country_code})`,
            isp: ipResponse.data.org || 'N/A'
        };
        
        return res.json(serverInfo);
    } catch (error) {
        console.error('Server Info Error:', error);
        return res.status(500).json({ error: `サーバー情報の取得処理中にエラー: ${error.message}` });
    }
});

app.listen(PORT, () => {
    console.log(`サーバーが起動しました: http://localhost:${PORT}`);
});
