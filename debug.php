<?php
/**
 * Cap 插件调试工具
 * 用于测试和调试 Cap API 连接问题
 */

// 防止直接访问
if (!defined('__TYPECHO_ROOT_DIR__')) {
    // 尝试多种路径来找到 Typecho 根目录
    $possiblePaths = [
        dirname(dirname(dirname(__FILE__))),  // usr/plugins/Cap -> 根目录
        dirname(dirname(dirname(dirname(__FILE__)))),  // 如果在子目录中
        realpath(dirname(__FILE__) . '/../../../'),
        realpath(dirname(__FILE__) . '/../../')
    ];
    
    foreach ($possiblePaths as $path) {
        if (file_exists($path . '/config.inc.php')) {
            define('__TYPECHO_ROOT_DIR__', $path);
            break;
        }
    }
    
    if (!defined('__TYPECHO_ROOT_DIR__')) {
        die('无法找到 Typecho 根目录');
    }
}

// 检查配置文件是否存在
if (!file_exists(__TYPECHO_ROOT_DIR__ . '/config.inc.php')) {
    die('配置文件不存在: ' . __TYPECHO_ROOT_DIR__ . '/config.inc.php');
}

try {
    require_once __TYPECHO_ROOT_DIR__ . '/config.inc.php';
} catch (Exception $e) {
    die('加载配置文件失败: ' . $e->getMessage());
}

// 简单的调试页面
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cap 插件调试工具</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }
        .container { max-width: 800px; margin: 0 auto; background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .section { margin: 20px 0; padding: 15px; border: 1px solid #ddd; border-radius: 5px; }
        .success { background: #d4edda; border-color: #c3e6cb; color: #155724; }
        .error { background: #f8d7da; border-color: #f5c6cb; color: #721c24; }
        .info { background: #d1ecf1; border-color: #bee5eb; color: #0c5460; }
        .warning { background: #fff3cd; border-color: #ffeaa7; color: #856404; }
        pre { background: #f8f9fa; padding: 10px; border-radius: 3px; overflow-x: auto; }
        button { background: #007bff; color: white; border: none; padding: 10px 20px; border-radius: 5px; cursor: pointer; margin: 5px; }
        button:hover { background: #0056b3; }
        .test-result { margin: 10px 0; padding: 10px; border-radius: 5px; }
        input[type="text"] { width: 100%; padding: 8px; margin: 5px 0; border: 1px solid #ddd; border-radius: 3px; }
    </style>
</head>
<body>
    <div class="container">
        <h1>Cap 插件调试工具</h1>
        
        <?php
        try {
            // 获取插件配置
            $options = \Widget\Options::alloc();
            $config = $options->plugin('Cap');
            
            echo '<div class="section info">';
            echo '<h3>当前配置</h3>';
            echo '<pre>';
            echo 'API 端点: ' . ($config->apiEndpoint ?? '未设置') . "\n";
            echo '脚本地址: ' . ($config->scriptUrl ?? '未设置') . "\n";
            echo '启用功能: ' . implode(', ', $config->enableActions ?? []) . "\n";
            echo '主题: ' . ($config->theme ?? 'light') . "\n";
            echo '使用 cURL: ' . ($config->useCurl ?? 'enable') . "\n";
            echo '</pre>';
            echo '</div>';
            
        } catch (Exception $e) {
            echo '<div class="section error">';
            echo '<h3>配置错误</h3>';
            echo '<p>无法读取插件配置: ' . $e->getMessage() . '</p>';
            echo '</div>';
        }
        ?>
        
        <div class="section">
            <h3>API 连接测试</h3>
            <form method="post" action="">
                <input type="hidden" name="action" value="test_api">
                <label>API 端点地址:</label>
                <input type="text" name="api_endpoint" value="<?php echo htmlspecialchars($config->apiEndpoint ?? 'https://captcha.gurl.eu.org'); ?>" placeholder="https://your-cap-server.com">
                <button type="submit">测试连接</button>
            </form>
            
            <?php
            if (isset($_POST['action']) && $_POST['action'] === 'test_api') {
                $apiEndpoint = trim($_POST['api_endpoint']);
                if (!empty($apiEndpoint)) {
                    echo '<div class="test-result">';
                    echo '<h4>测试结果:</h4>';
                    
                    // 测试 /api/challenge 端点
                    $challengeUrl = rtrim($apiEndpoint, '/') . '/api/challenge';
                    echo '<p><strong>测试 Challenge 端点:</strong> ' . $challengeUrl . '</p>';
                    
                    $ch = curl_init();
                    curl_setopt_array($ch, [
                        CURLOPT_URL => $challengeUrl,
                        CURLOPT_RETURNTRANSFER => true,
                        CURLOPT_POST => true,
                        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
                        CURLOPT_TIMEOUT => 10,
                        CURLOPT_SSL_VERIFYPEER => false,
                        CURLOPT_SSL_VERIFYHOST => false
                    ]);
                    
                    $response = curl_exec($ch);
                    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                    $error = curl_error($ch);
                    curl_close($ch);
                    
                    if ($response === false) {
                        echo '<div class="error">连接失败: ' . $error . '</div>';
                    } else {
                        echo '<div class="' . ($httpCode < 400 ? 'success' : 'error') . '">';
                        echo 'HTTP 状态码: ' . $httpCode . '<br>';
                        echo '响应长度: ' . strlen($response) . ' 字节<br>';
                        
                        $jsonData = json_decode($response, true);
                        if (json_last_error() === JSON_ERROR_NONE) {
                            echo '✅ 返回有效的 JSON 响应<br>';
                            echo '<pre>' . json_encode($jsonData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . '</pre>';
                        } else {
                            echo '⚠️ 响应不是有效的 JSON<br>';
                            echo '<pre>' . htmlspecialchars(substr($response, 0, 500)) . '</pre>';
                        }
                        echo '</div>';
                    }
                    echo '</div>';
                }
            }
            ?>
        </div>
        
        <div class="section">
            <h3>Token 验证测试</h3>
            <form method="post" action="">
                <input type="hidden" name="action" value="test_token">
                <label>API 端点地址:</label>
                <input type="text" name="api_endpoint" value="<?php echo htmlspecialchars($config->apiEndpoint ?? 'https://captcha.gurl.eu.org'); ?>">
                <label>验证 Token:</label>
                <input type="text" name="token" value="" placeholder="输入要验证的 token">
                <button type="submit">验证 Token</button>
            </form>
            
            <?php
            if (isset($_POST['action']) && $_POST['action'] === 'test_token') {
                $apiEndpoint = trim($_POST['api_endpoint']);
                $token = trim($_POST['token']);
                
                if (!empty($apiEndpoint) && !empty($token)) {
                    echo '<div class="test-result">';
                    echo '<h4>Token 验证结果:</h4>';
                    
                    $validateUrl = rtrim($apiEndpoint, '/') . '/api/validate';
                    $payload = json_encode(['token' => $token, 'keepToken' => false]);
                    
                    echo '<p><strong>验证端点:</strong> ' . $validateUrl . '</p>';
                    echo '<p><strong>请求数据:</strong> ' . $payload . '</p>';
                    
                    $ch = curl_init();
                    curl_setopt_array($ch, [
                        CURLOPT_URL => $validateUrl,
                        CURLOPT_RETURNTRANSFER => true,
                        CURLOPT_POST => true,
                        CURLOPT_POSTFIELDS => $payload,
                        CURLOPT_HTTPHEADER => [
                            'Content-Type: application/json',
                            'Accept: application/json'
                        ],
                        CURLOPT_TIMEOUT => 10,
                        CURLOPT_SSL_VERIFYPEER => false,
                        CURLOPT_SSL_VERIFYHOST => false
                    ]);
                    
                    $response = curl_exec($ch);
                    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                    $error = curl_error($ch);
                    curl_close($ch);
                    
                    if ($response === false) {
                        echo '<div class="error">验证请求失败: ' . $error . '</div>';
                    } else {
                        echo '<div class="' . ($httpCode < 400 ? 'success' : 'error') . '">';
                        echo 'HTTP 状态码: ' . $httpCode . '<br>';
                        echo '响应内容: <pre>' . htmlspecialchars($response) . '</pre>';
                        
                        $jsonData = json_decode($response, true);
                        if (json_last_error() === JSON_ERROR_NONE) {
                            if (isset($jsonData['success']) && $jsonData['success'] === true) {
                                echo '✅ Token 验证成功！';
                            } else {
                                echo '❌ Token 验证失败: ' . ($jsonData['error'] ?? '未知错误');
                            }
                        } else {
                            echo '⚠️ 响应格式错误: ' . json_last_error_msg();
                        }
                        echo '</div>';
                    }
                    echo '</div>';
                }
            }
            ?>
        </div>
        
        <div class="section">
            <h3>客户端集成测试</h3>
            <p>测试 Cap 客户端组件是否正常工作：</p>
            
            <?php if (!empty($config->apiEndpoint)): ?>
            <div id="cap-test-widget">
                <cap-widget 
                    id="cap-debug"
                    data-cap-api-endpoint="<?php echo rtrim($config->apiEndpoint, '/') . '/'; ?>"
                    data-theme="<?php echo $config->theme ?? 'light'; ?>">
                    正在加载验证组件...
                </cap-widget>
            </div>
            
            <div id="debug-output" style="margin-top: 20px; padding: 10px; background: #f8f9fa; border-radius: 5px;">
                <h4>调试输出:</h4>
                <div id="debug-log"></div>
            </div>
            
            <script src="<?php echo $config->scriptUrl ?? 'https://cdn.jsdmirror.com/gh/prosopo/captcha@latest/cap.min.js'; ?>"></script>
            <script>
            document.addEventListener('DOMContentLoaded', function() {
                const widget = document.querySelector("#cap-debug");
                const debugLog = document.querySelector("#debug-log");
                
                function log(message, type = 'info') {
                    const timestamp = new Date().toLocaleTimeString();
                    const logEntry = document.createElement('div');
                    logEntry.style.margin = '5px 0';
                    logEntry.style.padding = '5px';
                    logEntry.style.borderRadius = '3px';
                    
                    switch(type) {
                        case 'success':
                            logEntry.style.background = '#d4edda';
                            logEntry.style.color = '#155724';
                            break;
                        case 'error':
                            logEntry.style.background = '#f8d7da';
                            logEntry.style.color = '#721c24';
                            break;
                        default:
                            logEntry.style.background = '#d1ecf1';
                            logEntry.style.color = '#0c5460';
                    }
                    
                    logEntry.innerHTML = `<strong>[${timestamp}]</strong> ${message}`;
                    debugLog.appendChild(logEntry);
                    debugLog.scrollTop = debugLog.scrollHeight;
                }
                
                if (widget) {
                    log('Cap widget 已找到，等待加载...');
                    
                    widget.addEventListener("solve", function (e) {
                        const token = e.detail.token;
                        log(`✅ 验证完成！Token: ${token}`, 'success');
                    });
                    
                    widget.addEventListener("error", function (e) {
                        log(`❌ 验证失败: ${e.detail.message}`, 'error');
                    });
                    
                    widget.addEventListener("reset", function (e) {
                        log('🔄 验证已重置');
                    });
                    
                    // 检查组件是否正确加载
                    setTimeout(function() {
                        if (widget.innerHTML.includes('正在加载验证组件...')) {
                            log('⚠️ 验证组件可能未正确加载，请检查脚本地址', 'error');
                        } else {
                            log('✅ 验证组件已加载', 'success');
                        }
                    }, 3000);
                } else {
                    log('❌ 未找到 Cap widget', 'error');
                }
            });
            </script>
            <?php else: ?>
            <div class="warning">
                <p>请先在插件设置中配置 API 端点地址。</p>
            </div>
            <?php endif; ?>
        </div>
        
        <div class="section info">
            <h3>故障排查建议</h3>
            <ul>
                <li><strong>HTTP 500 错误:</strong> 通常表示 API 端点地址不正确或服务器内部错误</li>
                <li><strong>连接超时:</strong> 检查网络连接和防火墙设置</li>
                <li><strong>Token 格式错误:</strong> 确保 token 包含冒号分隔符 (格式: part1:part2)</li>
                <li><strong>验证组件不显示:</strong> 检查脚本地址是否可访问</li>
                <li><strong>JavaScript 错误:</strong> 打开浏览器开发者工具查看控制台错误</li>
            </ul>
            
            <h4>正确的 API 端点格式:</h4>
            <ul>
                <li>✅ https://captcha.gurl.eu.org</li>
                <li>✅ https://your-domain.com</li>
                <li>❌ https://captcha.gurl.eu.org/api (不要包含 /api)</li>
                <li>❌ https://captcha.gurl.eu.org/ (不要以斜杠结尾)</li>
            </ul>
        </div>
    </div>
</body>
</html>