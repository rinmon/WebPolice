<?php
/**
 * WebPolice - ウェブサイト技術分析ツール用関数
 */

// 一時ディレクトリの確認と作成
if (!file_exists('tmp')) {
    mkdir('tmp', 0755, true);
}

/**
 * ドメイン情報（WHOIS）を取得
 *
 * @param string $domain
 * @return array
 */
function getDomainInfo($domain) {
    try {
        // PHPのネイティブwhois関数を使用
        $whoisData = [];
        
        // PHPのwhois関数でWHOIS情報を取得
        $rawData = @shell_exec("whois " . escapeshellarg($domain));
        
        if (!$rawData) {
            return ['error' => 'WHOIS情報を取得できませんでした'];
        }
        
        // 生のWHOIS情報をパース
        $lines = explode("\n", $rawData);
        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line) || strpos($line, '%') === 0 || strpos($line, '#') === 0) {
                continue;
            }
            
            $parts = explode(':', $line, 2);
            if (count($parts) == 2) {
                $key = trim($parts[0]);
                $value = trim($parts[1]);
                
                // 日付フィールドの検出
                if (stripos($key, 'date') !== false || stripos($key, 'created') !== false || stripos($key, 'expir') !== false || stripos($key, 'updated') !== false) {
                    // 日付データの標準化
                    $date = strtotime($value);
                    if ($date) {
                        $value = date('Y-m-d', $date);
                    }
                }
                
                // 主要なフィールドを抽出
                if (stripos($key, 'domain name') !== false) {
                    $whoisData['domain_name'] = $value;
                } elseif (stripos($key, 'registrar') !== false) {
                    $whoisData['registrar'] = $value;
                } elseif (stripos($key, 'creation date') !== false || stripos($key, 'created') !== false) {
                    $whoisData['creation_date'] = $value;
                } elseif (stripos($key, 'expiration date') !== false || stripos($key, 'expir') !== false) {
                    $whoisData['expiration_date'] = $value;
                } elseif (stripos($key, 'updated date') !== false || stripos($key, 'updated') !== false) {
                    $whoisData['updated_date'] = $value;
                } elseif (stripos($key, 'status') !== false) {
                    $whoisData['status'] = isset($whoisData['status']) ? 
                        (is_array($whoisData['status']) ? array_merge($whoisData['status'], [$value]) : [$whoisData['status'], $value]) :
                        $value;
                } elseif (stripos($key, 'name server') !== false) {
                    $whoisData['name_servers'] = isset($whoisData['name_servers']) ? 
                        (is_array($whoisData['name_servers']) ? array_merge($whoisData['name_servers'], [$value]) : [$whoisData['name_servers'], $value]) :
                        $value;
                }
            }
        }
        
        if (empty($whoisData)) {
            // 代替APIを使用してみる
            $apiUrl = "https://www.whoisxmlapi.com/whoisserver/WhoisService?apiKey=at_demo&domainName=" . urlencode($domain) . "&outputFormat=JSON";
            $response = @file_get_contents($apiUrl);
            
            if ($response) {
                $data = json_decode($response, true);
                if (isset($data['WhoisRecord'])) {
                    $record = $data['WhoisRecord'];
                    
                    $whoisData['domain_name'] = $record['domainName'] ?? $domain;
                    $whoisData['registrar'] = $record['registrarName'] ?? 'N/A';
                    $whoisData['creation_date'] = $record['createdDate'] ?? 'N/A';
                    $whoisData['expiration_date'] = $record['expiresDate'] ?? 'N/A';
                    $whoisData['updated_date'] = $record['updatedDate'] ?? 'N/A';
                    
                    if (isset($record['status'])) {
                        $whoisData['status'] = is_array($record['status']) ? 
                            implode(', ', $record['status']) : 
                            $record['status'];
                    }
                    
                    if (isset($record['nameServers']) && isset($record['nameServers']['hostNames'])) {
                        $whoisData['name_servers'] = $record['nameServers']['hostNames'];
                    }
                }
            }
        }
        
        return empty($whoisData) ? ['error' => 'WHOIS情報を解析できませんでした'] : $whoisData;
    } catch (Exception $e) {
        return ['error' => 'WHOIS情報の取得中にエラーが発生しました: ' . $e->getMessage()];
    }
}

/**
 * 技術スタック情報を取得
 *
 * @param string $url
 * @return array
 */
function getTechStack($url) {
    try {
        // User-Agentを設定してサイトのHTMLを取得
        $context = stream_context_create([
            'http' => [
                'header' => 'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36'
            ]
        ]);
        
        $html = @file_get_contents($url, false, $context);
        
        if (!$html) {
            return ['error' => 'ウェブサイトのコンテンツを取得できませんでした'];
        }
        
        // HTTPヘッダー情報を取得
        $headers = [];
        foreach ($http_response_header as $header) {
            $parts = explode(':', $header, 2);
            if (count($parts) == 2) {
                $headers[strtolower(trim($parts[0]))] = trim($parts[1]);
            }
        }
        
        // 技術スタック情報を初期化
        $techStack = [
            'JavaScript Frameworks' => [],
            'Web Servers' => [],
            'Programming Languages' => [],
            'CMS' => [],
            'Analytics' => [],
            'CDN' => [],
            'OS' => []
        ];
        
        // HTMLの解析と技術検出
        
        // プログラミング言語の検出
        $techStack['Programming Languages'][] = 'HTML';
        
        if (strpos($html, '<script') !== false) {
            $techStack['Programming Languages'][] = 'JavaScript';
        }
        
        if (strpos($html, '.php') !== false || strpos($html, 'wordpress') !== false || strpos($html, 'WordPress') !== false) {
            $techStack['Programming Languages'][] = 'PHP';
        }
        
        if (strpos($html, '.aspx') !== false || strpos($html, '.asp') !== false) {
            $techStack['Programming Languages'][] = 'ASP.NET';
        }
        
        if (strpos($html, '.jsp') !== false || strpos($html, 'Java') !== false) {
            $techStack['Programming Languages'][] = 'Java';
        }
        
        if (strpos($html, '.py') !== false || strpos($html, 'django') !== false || strpos($html, 'Django') !== false || strpos($html, 'Flask') !== false) {
            $techStack['Programming Languages'][] = 'Python';
        }
        
        // JavaScript フレームワークの検出
        if (strpos($html, 'react') !== false || strpos($html, 'React') !== false || strpos($html, 'ReactDOM') !== false) {
            $techStack['JavaScript Frameworks'][] = 'React';
        }
        
        if (strpos($html, 'vue') !== false || strpos($html, 'Vue') !== false) {
            $techStack['JavaScript Frameworks'][] = 'Vue.js';
        }
        
        if (strpos($html, 'angular') !== false || strpos($html, 'Angular') !== false) {
            $techStack['JavaScript Frameworks'][] = 'Angular';
        }
        
        if (strpos($html, 'jquery') !== false || strpos($html, 'jQuery') !== false) {
            $techStack['JavaScript Frameworks'][] = 'jQuery';
        }
        
        // CMS の検出
        if (strpos($html, 'wordpress') !== false || strpos($html, 'WordPress') !== false || strpos($html, 'wp-content') !== false) {
            $techStack['CMS'][] = 'WordPress';
        }
        
        if (strpos($html, 'joomla') !== false || strpos($html, 'Joomla') !== false) {
            $techStack['CMS'][] = 'Joomla';
        }
        
        if (strpos($html, 'drupal') !== false || strpos($html, 'Drupal') !== false) {
            $techStack['CMS'][] = 'Drupal';
        }
        
        if (strpos($html, 'Magento') !== false || strpos($html, 'magento') !== false) {
            $techStack['CMS'][] = 'Magento';
        }
        
        // アナリティクスの検出
        if (strpos($html, 'google-analytics') !== false || strpos($html, 'googletagmanager') !== false || strpos($html, 'gtag') !== false || strpos($html, 'GA_TRACKING_ID') !== false) {
            $techStack['Analytics'][] = 'Google Analytics';
        }
        
        // CDNの検出
        if (strpos($html, 'cloudflare') !== false || strpos($html, 'Cloudflare') !== false) {
            $techStack['CDN'][] = 'Cloudflare';
        }
        
        if (strpos($html, 'akamai') !== false || strpos($html, 'Akamai') !== false) {
            $techStack['CDN'][] = 'Akamai';
        }
        
        if (strpos($html, 'cloudfront') !== false || strpos($html, 'CloudFront') !== false) {
            $techStack['CDN'][] = 'AWS CloudFront';
        }
        
        // Webサーバーの検出（レスポンスヘッダーから）
        if (isset($headers['server'])) {
            $server = $headers['server'];
            
            if (stripos($server, 'apache') !== false) {
                $techStack['Web Servers'][] = 'Apache';
            }
            
            if (stripos($server, 'nginx') !== false) {
                $techStack['Web Servers'][] = 'Nginx';
            }
            
            if (stripos($server, 'iis') !== false) {
                $techStack['Web Servers'][] = 'IIS';
            }
            
            // OSの検出を試みる
            if (stripos($server, 'ubuntu') !== false) {
                $techStack['OS'][] = 'Ubuntu';
            } elseif (stripos($server, 'debian') !== false) {
                $techStack['OS'][] = 'Debian';
            } elseif (stripos($server, 'centos') !== false) {
                $techStack['OS'][] = 'CentOS';
            } elseif (stripos($server, 'win') !== false) {
                $techStack['OS'][] = 'Windows';
            }
        }
        
        // 空の配列を削除
        foreach ($techStack as $key => $value) {
            if (empty($value)) {
                unset($techStack[$key]);
            }
        }
        
        return empty($techStack) ? ['error' => '技術スタック情報を検出できませんでした'] : $techStack;
    } catch (Exception $e) {
        return ['error' => '技術スタック情報の取得中にエラーが発生しました: ' . $e->getMessage()];
    }
}

/**
 * サイト存在開始時期を推定
 *
 * @param string $domain
 * @return string
 */
function getExistenceDate($domain) {
    try {
        // Wayback Machine APIを使用
        $url = "https://archive.org/wayback/available?url={$domain}";
        $response = @file_get_contents($url);
        
        if (!$response) {
            // WHOISの作成日を代替として使用
            $whoisData = getDomainInfo($domain);
            if (isset($whoisData['creation_date']) && !isset($whoisData['error'])) {
                return "ドメイン登録日: {$whoisData['creation_date']}";
            }
            return "Wayback Machineに記録が見つかりませんでした";
        }
        
        $data = json_decode($response, true);
        
        if (isset($data['archived_snapshots']) && isset($data['archived_snapshots']['closest'])) {
            $timestamp = $data['archived_snapshots']['closest']['timestamp'];
            
            // タイムスタンプからYYYYMMDDHHMMSS形式の日付を抽出
            $year = substr($timestamp, 0, 4);
            $month = substr($timestamp, 4, 2);
            $day = substr($timestamp, 6, 2);
            
            return "{$year}年{$month}月{$day}日頃";
        } else {
            // WHOISの作成日を代替として使用
            $whoisData = getDomainInfo($domain);
            if (isset($whoisData['creation_date']) && !isset($whoisData['error'])) {
                return "ドメイン登録日: {$whoisData['creation_date']}";
            }
            return "Wayback Machineに記録が見つかりませんでした";
        }
    } catch (Exception $e) {
        return "サイト存在開始時期の取得中にエラー: " . $e->getMessage();
    }
}

/**
 * SEO情報を取得
 *
 * @param string $url
 * @return array
 */
function getSeoInfo($url) {
    try {
        // User-Agentを設定してサイトのHTMLを取得
        $context = stream_context_create([
            'http' => [
                'header' => 'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36'
            ]
        ]);
        
        $html = @file_get_contents($url, false, $context);
        
        if (!$html) {
            return ['error' => 'ウェブサイトのコンテンツを取得できませんでした'];
        }
        
        // 簡易的なHTMLパーサーを作成
        $seoData = [];
        
        // タイトルの取得
        if (preg_match('/<title[^>]*>(.*?)<\/title>/is', $html, $matches)) {
            $seoData['title'] = trim($matches[1]);
        } else {
            $seoData['title'] = "見つかりません";
        }
        
        // メタディスクリプションの取得
        if (preg_match('/<meta[^>]*name=["|\']description["|\'][^>]*content=["|\']([^>]*?)["|\'][^>]*>/is', $html, $matches)) {
            $seoData['meta_description'] = trim($matches[1]);
        } else {
            $seoData['meta_description'] = "見つかりません";
        }
        
        // メタキーワードの取得
        if (preg_match('/<meta[^>]*name=["|\']keywords["|\'][^>]*content=["|\']([^>]*?)["|\'][^>]*>/is', $html, $matches)) {
            $seoData['meta_keywords'] = trim($matches[1]);
        } else {
            $seoData['meta_keywords'] = "見つかりません";
        }
        
        // H1タグの取得
        $h1Tags = [];
        if (preg_match_all('/<h1[^>]*>(.*?)<\/h1>/is', $html, $matches)) {
            foreach ($matches[1] as $match) {
                $h1Tags[] = trim(strip_tags($match));
            }
        }
        $seoData['h1_tags'] = $h1Tags;
        
        return $seoData;
    } catch (Exception $e) {
        return ['error' => 'SEO情報の解析中にエラーが発生しました: ' . $e->getMessage()];
    }
}

/**
 * DNS情報を取得
 *
 * @param string $domain
 * @return array
 */
function getDnsInfo($domain) {
    try {
        $dnsRecords = [];
        $recordTypes = ['A', 'AAAA', 'MX', 'NS', 'CNAME', 'TXT'];
        
        foreach ($recordTypes as $type) {
            $records = [];
            
            switch ($type) {
                case 'A':
                    $records = @dns_get_record($domain, DNS_A);
                    $records = array_map(function($record) {
                        return $record['ip'];
                    }, $records);
                    break;
                    
                case 'AAAA':
                    $records = @dns_get_record($domain, DNS_AAAA);
                    $records = array_map(function($record) {
                        return $record['ipv6'];
                    }, $records);
                    break;
                    
                case 'MX':
                    $records = @dns_get_record($domain, DNS_MX);
                    $records = array_map(function($record) {
                        return $record['pri'] . " " . $record['target'];
                    }, $records);
                    break;
                    
                case 'NS':
                    $records = @dns_get_record($domain, DNS_NS);
                    $records = array_map(function($record) {
                        return $record['target'];
                    }, $records);
                    break;
                    
                case 'CNAME':
                    $records = @dns_get_record($domain, DNS_CNAME);
                    $records = array_map(function($record) {
                        return $record['target'];
                    }, $records);
                    break;
                    
                case 'TXT':
                    $records = @dns_get_record($domain, DNS_TXT);
                    $records = array_map(function($record) {
                        return $record['txt'];
                    }, $records);
                    break;
            }
            
            $dnsRecords[$type] = empty($records) ? ["該当レコードなし"] : $records;
        }
        
        return $dnsRecords;
    } catch (Exception $e) {
        return ['error' => 'DNS情報の取得処理中にエラー: ' . $e->getMessage()];
    }
}

/**
 * サーバー情報を取得
 *
 * @param string $domain
 * @return array
 */
function getServerInfo($domain) {
    try {
        // IPアドレスを取得
        $records = @dns_get_record($domain, DNS_A);
        
        if (empty($records)) {
            return ['error' => 'IPアドレスが見つかりませんでした'];
        }
        
        $ipAddress = $records[0]['ip'];
        
        // IP情報APIを使用
        $apiUrl = "https://ipapi.co/{$ipAddress}/json/";
        $response = @file_get_contents($apiUrl);
        
        if (!$response) {
            return [
                'ip_address' => $ipAddress,
                'country' => 'N/A',
                'isp' => 'N/A'
            ];
        }
        
        $data = json_decode($response, true);
        
        return [
            'ip_address' => $ipAddress,
            'country' => isset($data['country_name']) && isset($data['country_code']) ? 
                "{$data['country_name']} ({$data['country_code']})" : 'N/A',
            'isp' => isset($data['org']) ? $data['org'] : 'N/A'
        ];
    } catch (Exception $e) {
        return ['error' => 'サーバー情報の取得処理中にエラー: ' . $e->getMessage()];
    }
}

/**
 * PDFを生成
 *
 * @param array $reportData
 * @return string|false
 */
function generatePDF($reportData) {
    try {
        require_once('fpdf/fpdf.php');
        
        if (!class_exists('FPDF')) {
            throw new Exception('FPDF library is not available');
        }
        
        $pdf = new FPDF();
        $pdf->AddPage();
        
        // フォント設定
        $pdf->SetFont('Arial', 'B', 16);
        $pdf->Cell(0, 10, 'Website Analysis Report', 0, 1, 'C');
        
        // 分析URL
        $pdf->SetFont('Arial', 'I', 12);
        $pdf->Cell(0, 10, 'URL: ' . ($reportData['url'] ?? 'N/A'), 0, 1, 'C');
        $pdf->Ln(5);
        
        // セクション
        $sections = [
            'domain_info' => 'Domain Information (WHOIS)',
            'tech_stack' => 'Technology Stack',
            'existence_date' => 'Estimated Website Age',
            'seo_info' => 'SEO Information',
            'dns_info' => 'DNS Records',
            'server_info' => 'Server Information'
        ];
        
        foreach ($sections as $key => $title) {
            if (!isset($reportData[$key])) continue;
            
            $data = $reportData[$key];
            
            // セクションヘッダー
            $pdf->SetFont('Arial', 'B', 14);
            $pdf->Cell(0, 10, $title, 0, 1, 'L');
            
            // セクションの内容
            $pdf->SetFont('Arial', '', 10);
            
            if ($key === 'existence_date') {
                // 文字列の場合
                $pdf->Cell(0, 8, $data, 0, 1);
            } elseif (is_array($data)) {
                if (isset($data['error'])) {
                    $pdf->SetTextColor(255, 0, 0);
                    $pdf->Cell(0, 8, 'Error: ' . $data['error'], 0, 1);
                    $pdf->SetTextColor(0, 0, 0);
                } else {
                    // テーブル形式のデータ
                    foreach ($data as $dataKey => $value) {
                        $pdf->SetFont('Arial', 'B', 10);
                        $label = $dataKey;
                        $pdf->Cell(60, 8, $label, 1);
                        
                        $pdf->SetFont('Arial', '', 10);
                        $text = is_array($value) ? implode(', ', $value) : $value;
                        
                        // 長いテキストの対応
                        if (strlen($text) > 60) {
                            $pdf->Cell(0, 8, substr($text, 0, 60) . '...', 1, 1);
                            $pdf->Cell(60, 8, '', 0);
                            $pdf->MultiCell(0, 8, $text, 1);
                        } else {
                            $pdf->Cell(0, 8, $text, 1, 1);
                        }
                    }
                }
            }
            
            $pdf->Ln(5);
        }
        
        // タイムスタンプを含むファイル名
        $filename = 'website-analysis-' . time() . '.pdf';
        $filepath = 'tmp/' . $filename;
        
        $pdf->Output('F', $filepath);
        
        return $filepath;
    } catch (Exception $e) {
        error_log('PDF Generation Error: ' . $e->getMessage());
        return false;
    }
}
