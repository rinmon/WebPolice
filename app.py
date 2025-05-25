from flask import Flask, render_template, request, jsonify, make_response
import whois
import builtwith
import requests
import datetime # For formatting date from Wayback Machine
from bs4 import BeautifulSoup
from weasyprint import HTML
from io import BytesIO
import dns.resolver
import socket # For gethostbyname

APP_VERSION = "0.9.0"

app = Flask(__name__)
app.config['APPLICATION_ROOT'] = '/web-analyzer'

# Helper function to get DNS information
def get_dns_info(domain):
    dns_records = {}
    record_types = ['A', 'AAAA', 'MX', 'NS', 'CNAME', 'TXT'] # SOA can be added if needed
    resolver = dns.resolver.Resolver()
    resolver.timeout = 3 # seconds
    resolver.lifetime = 5 # seconds

    for record_type in record_types:
        try:
            answers = resolver.resolve(domain, record_type)
            records = []
            for rdata in answers:
                if record_type == 'MX':
                    records.append(f"{rdata.preference} {rdata.exchange.to_text(omit_final_dot=True)}")
                elif record_type == 'SOA':
                    records.append(f"MNAME: {rdata.mname.to_text(omit_final_dot=True)}, RNAME: {rdata.rname.to_text(omit_final_dot=True)}, Serial: {rdata.serial}")
                elif record_type == 'TXT':
                    records.append(" ".join(b.decode('utf-8', 'ignore') for b in rdata.strings))
                else:
                    records.append(rdata.to_text(omit_final_dot=True))
            dns_records[record_type] = records if records else "見つかりません"
        except dns.resolver.NoAnswer:
            dns_records[record_type] = "該当レコードなし"
        except dns.resolver.NXDOMAIN:
            dns_records["error"] = "ドメインが存在しません (NXDOMAIN)"
            return dns_records # Stop if domain doesn't exist
        except dns.exception.Timeout:
            dns_records[record_type] = "タイムアウト"
        except dns.resolver.NoNameservers:
            dns_records[record_type] = "ネームサーバー情報なし"
        except Exception as e:
            app.logger.error(f"DNS query error for {domain} [{record_type}]: {str(e)}")
            dns_records[record_type] = f"エラー ({record_type})"
    return dns_records

# Helper function to get Server IP, Country, and ISP
def get_server_info(domain):
    server_info_data = {'ip_address': None, 'country': None, 'isp': None, 'error': None}
    api_url = None # For logging in case of error
    try:
        final_domain = domain
        try:
            # Attempt to resolve CNAME first to get the actual host for IP lookup
            # This is a simplified CNAME chase
            cname_answers = dns.resolver.resolve(domain, 'CNAME')
            if cname_answers:
                final_domain = cname_answers[0].target.to_text(omit_final_dot=True)
        except (dns.resolver.NoAnswer, dns.resolver.NXDOMAIN, dns.exception.Timeout, dns.resolver.NoNameservers):
            pass # Use original domain if CNAME lookup fails or no CNAME

        ip_address = socket.gethostbyname(final_domain)
        server_info_data['ip_address'] = ip_address
        
        api_url = f"http://ip-api.com/json/{ip_address}?fields=status,message,country,countryCode,isp,org,query"
        response = requests.get(api_url, timeout=5)
        response.raise_for_status()
        ip_data = response.json()
        
        if ip_data.get('status') == 'success':
            server_info_data['country'] = f"{ip_data.get('country', 'N/A')} ({ip_data.get('countryCode', 'N/A')})"
            isp_name = ip_data.get('isp', 'N/A')
            org_name = ip_data.get('org', '')
            if org_name and org_name.lower() != isp_name.lower():
                 server_info_data['isp'] = f"{isp_name} ({org_name})" if isp_name != 'N/A' else org_name
            else:
                server_info_data['isp'] = isp_name
        else:
            server_info_data['error'] = f"IP情報取得エラー(API): {ip_data.get('message', '不明なAPIエラー')}"
            
    except socket.gaierror:
        server_info_data['error'] = "IPアドレスの解決に失敗 (ドメイン名不正または疎通不可)"
    except requests.exceptions.RequestException as e:
        app.logger.error(f"IP API request error for {domain} (URL: {api_url}): {str(e)}")
        server_info_data['error'] = f"IP情報APIへのアクセスに失敗"
    except Exception as e:
        app.logger.error(f"Unexpected error in get_server_info for {domain}: {str(e)}")
        server_info_data['error'] = f"サーバー情報取得中に予期せぬエラー"
    return server_info_data


@app.route('/')
def index():
    return render_template('index.html', app_version=APP_VERSION)

@app.route('/analyze', methods=['POST'])
def analyze_url():
    data = request.get_json()
    url_to_analyze = data.get('url')

    if not url_to_analyze:
        return jsonify({'error': 'URLが必要です。'}), 400

    # Add protocol if missing
    if not url_to_analyze.startswith(('http://', 'https://')):
        url_to_analyze = 'http://' + url_to_analyze
    
    domain = url_to_analyze.split('//')[-1].split('/')[0]

    report = {
        'url': url_to_analyze,
        'domain_info': {},
        'tech_stack': {},
        'existence_date': None,
        'seo_info': {},
        'dns_info': {},
        'server_info': {}
    }

    # 1. WHOIS情報
    try:
        domain_info = whois.whois(domain)
        # Convert datetime objects to strings for JSON serialization
        report['domain_info'] = {k: (v.isoformat() if hasattr(v, 'isoformat') else v) for k, v in domain_info.items() if v is not None}
    except Exception as e:
        report['domain_info']['error'] = f"WHOIS情報の取得に失敗しました: {str(e)}"

    # 2. 使用技術の分析 (builtwith)
    try:
        tech_info = builtwith.parse(url_to_analyze)
        app.logger.info(f"Builtwith raw output for {url_to_analyze}: {tech_info}") # Log raw output
        report['tech_stack'] = tech_info if tech_info else {}
    except Exception as e:
        report['tech_stack']['error'] = f"技術情報の分析に失敗しました: {str(e)}"

    # 3. サイト存在開始時期 (Wayback Machine)
    try:
        # Use the domain for Wayback Machine, not the full URL with path
        wayback_url = f"http://web.archive.org/cdx/search/cdx?url={domain}&output=json&fl=timestamp&limit=1&sort=asc"
        response = requests.get(wayback_url, timeout=10)
        response.raise_for_status() # Raise an exception for HTTP errors
        data = response.json()
        if data and len(data) > 1 and data[1]: # Check if data[1] (first snapshot) exists
            timestamp_str = data[1][0]
            # Timestamp is like YYYYMMDDHHMMSS
            year = int(timestamp_str[0:4])
            month = int(timestamp_str[4:6])
            day = int(timestamp_str[6:8])
            report['existence_date'] = f"{year}年{month}月{day}日頃"
        else:
            report['existence_date'] = "Wayback Machineに記録が見つかりませんでした。"
    except requests.exceptions.RequestException as e:
        report['existence_date'] = f"Wayback Machineへのアクセスに失敗しました: {str(e)}"
    except Exception as e:
        report['existence_date'] = f"サイト存在開始時期の取得中にエラー: {str(e)}"

    # 4. 基本的なSEO情報
    try:
        headers = {'User-Agent': 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36'}
        # Use url_to_analyze which includes the protocol
        page_response = requests.get(url_to_analyze, headers=headers, timeout=10)
        page_response.raise_for_status()
        soup = BeautifulSoup(page_response.content, 'html.parser')

        seo_data = {}
        # Title
        title_tag = soup.find('title')
        seo_data['title'] = title_tag.string.strip() if title_tag and title_tag.string else "見つかりません"

        # Meta Description
        meta_desc_tag = soup.find('meta', attrs={'name': 'description'})
        seo_data['meta_description'] = meta_desc_tag.get('content').strip() if meta_desc_tag and meta_desc_tag.get('content') else "見つかりません"
        
        # Meta Keywords (less important nowadays, but for completeness)
        meta_keywords_tag = soup.find('meta', attrs={'name': 'keywords'})
        seo_data['meta_keywords'] = meta_keywords_tag.get('content').strip() if meta_keywords_tag and meta_keywords_tag.get('content') else "見つかりません"

        # H1 Tags
        h1_tags = soup.find_all('h1')
        seo_data['h1_tags'] = [h1.get_text(strip=True) for h1 in h1_tags] if h1_tags else []
        
        report['seo_info'] = seo_data

    except requests.exceptions.RequestException as e:
        report['seo_info']['error'] = f"ページコンテンツの取得に失敗しました: {str(e)}"
    except Exception as e:
        report['seo_info']['error'] = f"SEO情報の解析中にエラーが発生しました: {str(e)}"

    # 5. DNS情報
    try:
        report['dns_info'] = get_dns_info(domain)
    except Exception as e:
        app.logger.error(f"Error calling get_dns_info for {domain}: {str(e)}", exc_info=True)
        report['dns_info']['error'] = f"DNS情報の取得処理中にエラー: {str(e)}"

    # 6. サーバー情報 (IP, 国, ISP)
    try:
        report['server_info'] = get_server_info(domain)
    except Exception as e:
        app.logger.error(f"Error calling get_server_info for {domain}: {str(e)}", exc_info=True)
        report['server_info']['error'] = f"サーバー情報の取得処理中にエラー: {str(e)}"

    # --- ここに他の分析機能を追加していく --- 

    return jsonify(report)


@app.route('/download_pdf', methods=['POST'])
def download_pdf():
    report_data = request.get_json()
    if not report_data:
        return jsonify({'error': 'レポートデータがありません。'}), 400

    try:
        url_analyzed = report_data.get('url', 'N/A')
        # HTMLコンテンツの生成
        html_content = f"""
        <!DOCTYPE html>
        <html lang="ja">
        <head>
            <meta charset="UTF-8">
            <title>ウェブサイト分析レポート: {url_analyzed}</title>
            <style>
                body {{ font-family: 'Helvetica Neue', Arial, 'Hiragino Kaku Gothic ProN', 'Hiragino Sans', Meiryo, sans-serif; line-height: 1.6; padding: 20px; font-size: 10pt; color: #333; }}
                h1 {{ color: #2c3e50; border-bottom: 2px solid #3498db; padding-bottom: 10px; font-size: 20pt; margin-bottom: 25px; text-align: center; }}
                h2 {{ color: #3498db; margin-top: 30px; border-bottom: 1px solid #bdc3c7; padding-bottom: 8px; font-size: 15pt; margin-bottom: 15px; }}
                .section {{ margin-bottom: 20px; padding-left: 10px; background-color: #f9f9f9; border: 1px solid #ecf0f1; border-radius: 5px; padding:15px; }}
                .section p {{ margin: 4px 0; }}
                .section strong {{ display: inline-block; min-width: 170px; font-weight: bold; color: #2980b9; }}
                ul {{ padding-left: 20px; margin-top: 5px; list-style-type: square; }}
                li {{ margin-bottom: 4px; }}
                .error {{ color: #e74c3c; font-weight: bold; background-color: #fdd; padding:10px; border-radius:3px; }}
                table {{ width: 100%; border-collapse: collapse; margin-top: 10px; }}
                th, td {{ border: 1px solid #bdc3c7; padding: 8px; text-align: left; vertical-align: top; }}
                th {{ background-color: #ecf0f1; font-weight: bold; color: #2c3e50; min-width:150px; }}
                .url-header {{ font-size: 12pt; text-align:center; margin-bottom:20px; color: #7f8c8d; }}
            </style>
        </head>
        <body>
            <h1>ウェブサイト分析レポート</h1>
            <p class="url-header"><strong>分析対象URL:</strong> {url_analyzed}</p>
            
        """

        # ドメイン情報 (WHOIS)
        html_content += "<h2>ドメイン情報 (WHOIS)</h2><div class='section'>"
        domain_info = report_data.get('domain_info')
        if domain_info:
            if domain_info.get('error'):
                html_content += f"<p class='error'>{domain_info['error']}</p>"
            else:
                html_content += "<table>"
                for key, value in domain_info.items():
                    if key.lower() == 'error': continue
                    val_str = ", ".join(map(str, value)) if isinstance(value, list) else str(value)
                    html_content += f"<tr><th>{key.replace('_', ' ').title()}</th><td>{val_str if val_str else 'N/A'}</td></tr>"
                html_content += "</table>"
        else:
            html_content += "<p>利用可能な情報はありません。</p>"
        html_content += "</div>"

        # 使用技術
        html_content += "<h2>使用技術</h2><div class='section'>"
        tech_stack = report_data.get('tech_stack')
        if tech_stack:
            if tech_stack.get('error'):
                html_content += f"<p class='error'>{tech_stack['error']}</p>"
            else:
                html_content += "<table>"
                for category, techs in tech_stack.items():
                    if category.lower() == 'error': continue
                    display_category = category.replace('-', ' ').title()
                    # カテゴリ名の日本語マッピング (script.jsから一部抜粋・拡張)
                    category_map = {
                        "Js Frameworks": "JavaScript フレームワーク", "Web Servers": "ウェブサーバー",
                        "Programming Languages": "プログラミング言語", "Cms": "CMS",
                        "Analytics": "アクセス解析", "Advertising": "広告", "Widgets": "ウィジェット",
                        "Font Scripts": "フォントスクリプト", "Tag Managers": "タグマネージャー",
                        "Security": "セキュリティ", "Cdn": "CDN", "Seo": "SEOツール", 
                        "Marketing Automation": "マーケティングオートメーション", "Ecommerce": "Eコマース"
                    }
                    display_category = category_map.get(display_category, display_category)
                    tech_list = ", ".join(techs) if isinstance(techs, list) else str(techs)
                    html_content += f"<tr><th>{display_category}</th><td>{tech_list if tech_list else 'N/A'}</td></tr>"
                html_content += "</table>"
        else:
            html_content += "<p>利用可能な情報はありません。</p>"
        html_content += "</div>"
        
        # サイト存在開始時期
        html_content += "<h2>サイト存在開始時期 (推定)</h2><div class='section'>"
        existence_date = report_data.get('existence_date', '利用可能な情報はありません。')
        html_content += f"<p><strong>推定開始日:</strong> {existence_date}</p>"
        html_content += "</div>"

        # SEO情報
        html_content += "<h2>SEO情報</h2><div class='section'>"
        seo_info = report_data.get('seo_info')
        if seo_info:
            if seo_info.get('error'):
                html_content += f"<p class='error'>{seo_info['error']}</p>"
            else:
                html_content += "<table>"
                seo_map = {
                    'title': 'タイトル',
                    'meta_description': 'メタディスクリプション',
                    'meta_keywords': 'メタキーワード',
                    'h1_tags': 'H1 タグ'
                }
                for key, display_name in seo_map.items():
                    value = seo_info.get(key)
                    if key == 'h1_tags':
                        val_str = (", ".join(value) if value else "見つかりません") if isinstance(value, list) else (str(value) if value else "見つかりません")
                    else:
                        val_str = str(value) if value else "見つかりません"
                    html_content += f"<tr><th>{display_name}</th><td>{val_str}</td></tr>"
                html_content += "</table>"
        else:
            html_content += "<p>利用可能な情報はありません。</p>"
        html_content += "</div>"

        # DNS情報
        html_content += "<h2>DNS情報</h2><div class='section'>"
        dns_info = report_data.get('dns_info')
        if dns_info:
            if dns_info.get('error') and not any(k for k in dns_info if k != 'error'):
                html_content += f"<p class='error'>{dns_info['error']}</p>"
            else:
                html_content += "<table>"
                if dns_info.get('error'): # General error for the whole block
                     html_content += f"<tr><td colspan='2'><p class='error'>DNS情報取得エラー: {dns_info['error']}</p></td></tr>"
                dns_order = ['A', 'AAAA', 'CNAME', 'MX', 'NS', 'TXT', 'SOA']
                for record_type in dns_order:
                    if record_type in dns_info:
                        value = dns_info[record_type]
                        val_str = ""
                        if isinstance(value, str) and ("エラー" in value or "タイムアウト" in value or "見つかりません" in value or "該当レコードなし" in value or "ドメインが存在しません" in value or record_type.lower() in value.lower()):
                            val_str = f"<span class='error-inline' style='color: #c0392b;'>{value}</span>" # Added inline style for error emphasis
                        elif isinstance(value, list):
                            if not value or value == ["見つかりません"] or value == ["該当レコードなし"]:
                                val_str = value[0] if value else "N/A"
                            else:
                                val_str = "<ul>" + "".join(f"<li>{v}</li>" for v in value) + "</ul>"
                        else:
                            val_str = str(value)
                        html_content += f"<tr><th>{record_type} レコード</th><td>{val_str}</td></tr>"
                html_content += "</table>"
        else:
            html_content += "<p>利用可能な情報はありません。</p>"
        html_content += "</div>"

        # サーバー情報
        html_content += "<h2>サーバー情報</h2><div class='section'>"
        server_info = report_data.get('server_info')
        if server_info:
            if server_info.get('error'):
                html_content += f"<p class='error'>{server_info['error']}</p>"
            else:
                html_content += "<table>"
                server_map = {
                    'ip_address': 'IPアドレス',
                    'country': '国',
                    'isp': 'ISP (プロバイダ)'
                }
                for key, display_name in server_map.items():
                    value = server_info.get(key)
                    html_content += f"<tr><th>{display_name}</th><td>{str(value) if value else 'N/A'}</td></tr>"
                html_content += "</table>"
        else:
            html_content += "<p>利用可能な情報はありません。</p>"
        html_content += "</div>"

        html_content += """
        </body>
        </html>
        """
        
        pdf_file = BytesIO()
        HTML(string=html_content).write_pdf(pdf_file)
        pdf_file.seek(0)

        response = make_response(pdf_file.getvalue())
        response.headers['Content-Type'] = 'application/pdf'
        
        domain_for_filename = 'report'
        if url_analyzed and url_analyzed != 'N/A':
            try:
                domain_for_filename = url_analyzed.split('//')[-1].split('/')[0].replace('www.', '')
            except Exception:
                pass # Keep 'report' as default
        
        date_str = datetime.datetime.now().strftime("%Y-%m-%d")
        response.headers['Content-Disposition'] = f'attachment; filename="website-analysis-{domain_for_filename}-{date_str}.pdf"'
        
        return response

    except Exception as e:
        app.logger.error(f"PDF Generation Error: {str(e)}", exc_info=True)
        return jsonify({'error': f'PDF生成中にエラーが発生しました: {str(e)}'}), 500


if __name__ == '__main__':
    # For development, you can still use app.run.
    # For production, use a WSGI server like Gunicorn and this block might not be executed.
    app.run(host='0.0.0.0', port=5001, debug=True)
