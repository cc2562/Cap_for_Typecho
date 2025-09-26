<?php

/**
 * Cap 人机验证插件
 *
 * @package Cap
 * @author CCRice
 * @version 1.0.0
 * @link https://github.com/prosopo/captcha
 */


use Typecho\Plugin\PluginInterface;
use Typecho\Widget\Helper\Form;
use Typecho\Widget\Helper\Form\Element\Text;
use Typecho\Widget\Helper\Form\Element\Radio;
use Typecho\Widget\Helper\Form\Element\Checkbox;
use Widget\Options;
use Typecho\Common;
use Utils\PasswordHash;


if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}


class Cap_Plugin implements PluginInterface
{
    /**
     * 是否启用救援模式
     * 启用后，将跳过登录验证，适用于无法通过验证时临时排查问题
     */
    private static $rescueMode = false;

    /**
     * 激活插件方法,如果激活失败,直接抛出异常
     *
     * @access public
     * @return void
     * @throws \Typecho\Plugin\Exception
     */
    public static function activate()
    {
        // 初始化默认配置，避免配置未找到的错误
        $options = Options::alloc();
        $config = array(
            'apiEndpoint' => 'https://captcha.gurl.eu.org/api',
            'scriptUrl' => 'https://captcha.gurl.eu.org/cap.min.js',
            'theme' => 'light',
            'enableActions' => array(),
            'useCurl' => 'enable'
        );
        
        // 使用 Typecho 的配置存储方法
        $db = \Typecho\Db::get();
        $prefix = $db->getPrefix();
        
        // 检查配置是否已存在
        try {
            $existingConfig = $db->fetchRow($db->select()->from('table.options')->where('name = ?', 'plugin:Cap'));
            if (!$existingConfig) {
                // 配置不存在，插入默认配置
                $db->query($db->insert('table.options')->rows(array(
                    'name' => 'plugin:Cap',
                    'user' => 0,
                    'value' => serialize($config)
                )));
            }
        } catch (Exception $e) {
            // 如果出错，使用备用方法
            $options->__set('plugin:Cap', $config);
        }
        
        \Typecho\Plugin::factory('Widget\Feedback')->comment = [__CLASS__, 'verifyCap_comment'];
        \Typecho\Plugin::factory('Widget\Archive')->header = [__CLASS__, 'header'];
        \Typecho\Plugin::factory('admin/footer.php')->end = [__CLASS__, 'output_login'];
        \Typecho\Plugin::factory('Widget\User')->hashValidate = [__CLASS__, 'verifyCap_login'];
    }

    /**
     * 禁用插件方法,如果禁用失败,直接抛出异常
     *
     * @static
     * @access public
     * @return void
     * @throws \Typecho\Plugin\Exception
     */
    public static function deactivate()
    {
    }

    /**
     * 个人用户的配置面板
     *
     * @access public
     * @param Form $form
     * @return void
     */
    public static function personalConfig(Form $form)
    {
    }

    /**
     * 获取插件配置面板
     *
     * @access public
     * @param Form $form 配置面板
     * @return void
     */
    public static function config(Form $form)
    {
        $apiEndpoint = new Text(
            'apiEndpoint', 
            NULL, 
            'https://captcha.gurl.eu.org/api', 
            _t('Cap API 端点'), 
            _t('Cap 验证服务的 API 端点地址，例如：https://captcha.gurl.eu.org/api（不要以斜杠结尾）')
        );
        
        $scriptUrl = new Text(
            'scriptUrl', 
            NULL, 
            'https://captcha.gurl.eu.org/cap.min.js',
            _t('Cap 脚本地址'), 
            _t('Cap 客户端脚本的 URL 地址，如果大陆访问困难可以自行托管并替换里面的cdn为国内镜像')
        );
        
        $enableActions = new Checkbox(
            'enableActions', 
            array(
                "login" => _t('登录'),
                "comment" => _t('评论')
            ), 
            array(), 
            _t('在哪些地方启用验证'), 
            _t('给评论启用验证后需要修改主题模板，在评论表单中调用 Cap_Plugin::output() 方法')
        );
        
        $theme = new Radio(
            'theme', 
            array(
                'light' => _t('亮色'),
                'dark' => _t('暗色')
            ), 
            'light', 
            _t('主题'), 
            _t('选择验证码组件的主题样式')
        );
        
        $useCurl = new Radio(
            'useCurl', 
            array(
                'enable' => _t('启用'),
                'disable' => _t('禁用')
            ), 
            'enable', 
            _t('使用 cURL'), 
            _t('(建议启用) 启用后将会使用 cURL 发送请求，但是需要 PHP 的 cURL 拓展。默认使用 file_get_contents 函数')
        );

        $form->addInput($apiEndpoint);
        $form->addInput($scriptUrl);
        $form->addInput($enableActions);
        $form->addInput($theme);
        $form->addInput($useCurl);
    }

    public static function header()
    {
        try {
            $config = Options::alloc()->plugin('Cap');
            if (!empty($config->scriptUrl)) {
                $scriptUrl = $config->scriptUrl;
                echo "<script src=\"{$scriptUrl}\" async defer></script>";
            }
        } catch (Exception $e) {
            // 配置未找到时静默处理，不影响页面加载
            return;
        }
    }

    /**
     * 展示验证码
     */
    public static function output()
    {
        try {
            $config = Options::alloc()->plugin('Cap');
            if (!in_array('comment', $config->enableActions)) {
                return;
            }
            
            $apiEndpoint = rtrim($config->apiEndpoint, '/') . '/';
            
            if (empty($apiEndpoint)) {
                throw new \Typecho\Plugin\Exception(_t('请先设置 Cap API 端点!'));
            }

            $theme = isset($config->theme) ? $config->theme : 'light';
            
            echo <<<EOL
            <div id="cap-widget">
                <cap-widget 
                    id="cap-comment"
                    data-cap-api-endpoint="{$apiEndpoint}"
                    data-theme="{$theme}">
                    正在加载验证组件...
                </cap-widget>
            </div>
            <script>
            (function() {
                'use strict';
                
                let capToken = '';
                let tokenInput = null;
                
                function createTokenInput() {
                    if (tokenInput) return tokenInput;
                    
                    tokenInput = document.createElement('input');
                    tokenInput.type = 'hidden';
                    tokenInput.name = 'cap-token';
                    tokenInput.id = 'cap-token-input';
                    
                    return tokenInput;
                }
                
                function findCommentForm() {
                    // 按优先级查找评论表单
                    const selectors = [
                        '#comment-form',
                        'form[action*="comment"]',
                        'form[id*="comment"]',
                        'form[class*="comment"]'
                    ];
                    
                    for (let selector of selectors) {
                        const form = document.querySelector(selector);
                        if (form) return form;
                    }
                    
                    // 查找包含评论相关字段的表单
                    const forms = document.querySelectorAll('form[method="post"], form[method="POST"]');
                    for (let form of forms) {
                        if (form.querySelector('textarea[name="text"]') || 
                            form.querySelector('input[name="author"]') ||
                            form.querySelector('textarea[name="comment"]') ||
                            form.querySelector('input[name="mail"]') ||
                            form.querySelector('input[name="url"]')) {
                            return form;
                        }
                    }
                    
                    return null;
                }
                
                function addTokenToForm(token) {
                    capToken = token;
                    
                    if (!tokenInput) {
                        tokenInput = createTokenInput();
                    }
                    
                    tokenInput.value = token;
                    
                    // 查找评论表单
                    const commentForm = findCommentForm();
                    
                    if (commentForm && !commentForm.contains(tokenInput)) {
                        commentForm.appendChild(tokenInput);
                    } else if (!commentForm) {
                        // 如果找不到表单，添加到 widget 的父容器
                        const widget = document.querySelector("#cap-comment");
                        if (widget && widget.parentNode && !widget.parentNode.contains(tokenInput)) {
                            widget.parentNode.appendChild(tokenInput);
                            console.log('⚠️ Cap token 已添加到 widget 容器（未找到评论表单）');
                        }
                    }
                    
                    // 确保所有可能的表单都包含 token
                    const allForms = document.querySelectorAll('form[method="post"], form[method="POST"]');
                    allForms.forEach(function(form) {
                        if ((form.querySelector('textarea[name="text"]') || 
                             form.querySelector('input[name="author"]') ||
                             form.querySelector('textarea[name="comment"]')) &&
                            !form.querySelector('input[name="cap-token"]')) {
                            
                            const clonedInput = tokenInput.cloneNode(true);
                            clonedInput.id = 'cap-token-input-' + Math.random().toString(36).substr(2, 9);
                            form.appendChild(clonedInput);
                            console.log('✅ Cap token 已添加到额外表单');
                        }
                    });
                }
                
                function setupFormValidation() {
                    // 监听所有可能的表单提交
                    document.addEventListener('submit', function(e) {
                        const form = e.target;
                        
                        // 检查是否是评论表单
                        if (form.querySelector('textarea[name="text"]') || 
                            form.querySelector('input[name="author"]') ||
                            form.querySelector('textarea[name="comment"]')) {
                            
                            const tokenField = form.querySelector('input[name="cap-token"]');
                            
                            if (!tokenField || !tokenField.value) {
                                console.error('❌ 表单提交失败：未找到 Cap token');
                                e.preventDefault();
                                alert('请先完成人机验证');
                                return false;
                            }
                            
                           
                        }
                    });
                }
                
                function initCapWidget() {
                    const widget = document.querySelector("#cap-comment");
                    if (!widget) {
                        console.error('❌ 未找到 Cap widget');
                        return;
                    }
                    
                    widget.addEventListener("solve", function (e) {
                        const token = e.detail.token;
                        addTokenToForm(token);
                    });
                    
                    widget.addEventListener("error", function (e) {
                        console.error('❌ Cap 验证失败:', e.detail.message);
                        capToken = '';
                        if (tokenInput) {
                            tokenInput.value = '';
                        }
                    });
                    
                    widget.addEventListener("reset", function (e) {
                        console.log('🔄 Cap 验证已重置');
                        capToken = '';
                        if (tokenInput) {
                            tokenInput.value = '';
                        }
                    });
                }
                
                // 初始化
                if (document.readyState === 'loading') {
                    document.addEventListener('DOMContentLoaded', function() {
                        initCapWidget();
                        setupFormValidation();
                    });
                } else {
                    initCapWidget();
                    setupFormValidation();
                }
                
                // 支持 PJAX 等动态加载
                if (window.addEventListener) {
                    window.addEventListener('pjax:complete', function() {
                        setTimeout(function() {
                            initCapWidget();
                            setupFormValidation();
                        }, 100);
                    });
                }
                
            })();
            </script>
            EOL;
        } catch (Exception $e) {
            echo "<div style='color: red; padding: 10px; border: 1px solid red; margin: 10px 0;'>Cap 插件配置错误: " . $e->getMessage() . "</div>";
        }
    }

    public static function output_login()
    {
        try {
            // 判断是否登录页面
            $currentRequestUrl = Options::alloc()->request->getRequestUrl();
            $config = Options::alloc()->plugin('Cap');
            
            if (
                !stripos($currentRequestUrl, 'login.php') ||
                !in_array('login', $config->enableActions)
            ) {
                return;
            }

            $apiEndpoint = rtrim($config->apiEndpoint, '/') . '/';
            
            if (empty($apiEndpoint)) {
                return;
            }

            $theme = isset($config->theme) ? $config->theme : 'light';
            
            echo <<<EOF
            <script>
                document.addEventListener('DOMContentLoaded', function() {
                    var passwordField = document.getElementById('password');
                    if (passwordField && passwordField.parentNode) {
                        var capDiv = document.createElement('div');
                        capDiv.id = 'cap-widget-login';
                        capDiv.innerHTML = '<cap-widget id="cap-login" data-cap-api-endpoint="{$apiEndpoint}" data-theme="{$theme}">正在加载验证组件...</cap-widget>';
                        passwordField.parentNode.insertBefore(capDiv, passwordField.nextSibling);
                        
                        // 监听验证完成事件
                        const widget = document.querySelector("#cap-login");
                        if (widget) {
                            widget.addEventListener("solve", function (e) {
                                const token = e.detail.token;
                                // 将 token 添加到登录表单中
                                let tokenInput = document.querySelector('input[name="cap-token"]');
                                if (!tokenInput) {
                                    tokenInput = document.createElement('input');
                                    tokenInput.type = 'hidden';
                                    tokenInput.name = 'cap-token';
                                    passwordField.parentNode.appendChild(tokenInput);
                                }
                                tokenInput.value = token;
                                console.log('Cap 登录验证完成');
                            });
                        }
                    }
                });
            </script>
EOF;
        } catch (Exception $e) {
            // 配置未找到时静默处理，不影响登录页面
            return;
        }
    }

    public static function verifyCap_comment($comments, $obj)
    {
        try {
            $userObj = $obj->widget('Widget\User');
            $config = Options::alloc()->plugin('Cap');
            
            // 管理员或未启用评论验证时跳过
            if (($userObj->hasLogin() && $userObj->pass('administrator', true)) ||
                !in_array('comment', $config->enableActions)
            ) {
                return $comments;
            }
            
            // 详细调试信息
            error_log("=== Cap Plugin Comment Verification Debug ===");
            error_log("POST data keys: " . implode(', ', array_keys($_POST)));
            error_log("POST data: " . json_encode($_POST, JSON_UNESCAPED_UNICODE));
            error_log("Request method: " . $_SERVER['REQUEST_METHOD']);
            error_log("Content type: " . ($_SERVER['CONTENT_TYPE'] ?? 'not set'));
            
            // 检查是否提交了验证码 token
            if (!isset($_POST['cap-token'])) {
                error_log("❌ cap-token not found in POST data");
                
                // 检查是否在其他地方
                $allInputs = array_merge($_POST, $_GET, $_REQUEST);
                $foundToken = false;
                foreach ($allInputs as $key => $value) {
                    if (strpos($key, 'cap') !== false || strpos($key, 'token') !== false) {
                        error_log("Found related field: $key = $value");
                        $foundToken = true;
                    }
                }
                
                if (!$foundToken) {
                    error_log("No cap-related fields found in request");
                }
                
                throw new \Typecho\Plugin\Exception(_t('请完成人机验证后再发布评论（未找到验证token）'));
            }
            
            $token = trim($_POST['cap-token']);
            error_log("✅ Found cap-token: " . substr($token, 0, 20) . "...");
            
            if (empty($token)) {
                error_log("❌ cap-token is empty");
                throw new \Typecho\Plugin\Exception(_t('验证码不能为空，请重新验证'));
            }
            
            // 验证 token
            error_log("🔍 Starting token validation...");
            $resp = self::validateCapToken($token);
            
            if (!$resp) {
                error_log("❌ validateCapToken returned null/false");
                throw new \Typecho\Plugin\Exception(_t('验证服务无响应，请稍后重试'));
            }
            
            if (!isset($resp['success'])) {
                error_log("❌ Response missing 'success' field: " . json_encode($resp));
                throw new \Typecho\Plugin\Exception(_t('验证服务响应格式错误'));
            }
            
            if ($resp['success'] !== true) {
                error_log("❌ Validation failed: " . json_encode($resp));
                $errorMsg = self::getCapResultMsg($resp);
                throw new \Typecho\Plugin\Exception(_t($errorMsg));
            }
            
            error_log("✅ Cap verification successful");
            return $comments;
            
        } catch (\Typecho\Plugin\Exception $e) {
            error_log("❌ Cap Plugin Exception: " . $e->getMessage());
            throw $e;
        } catch (Exception $e) {
            error_log("❌ Cap Plugin System Error: " . $e->getMessage());
            error_log("Stack trace: " . $e->getTraceAsString());
            
            // 系统错误时不阻止评论，但记录错误
            return $comments;
        }
    }

    public static function verifyCap_login($password, $hash)
    {
        try {
            $config = Options::alloc()->plugin('Cap');
            $enableCap = in_array('login', $config->enableActions);
            
            if ($enableCap && !self::$rescueMode) {
                if (isset($_POST['cap-token'])) {
                    if (empty($_POST['cap-token'])) {
                        \Widget\Notice::alloc()->set(_t('请先完成验证'), 'error');
                        Options::alloc()->response->goBack();
                    }
                    
                    $resp = self::validateCapToken($_POST['cap-token']);
                    
                    if (!$resp || !isset($resp['success']) || $resp['success'] !== true) {
                        $errorMsg = self::getCapResultMsg($resp);
                        self::loginFailed($errorMsg);
                        return false;
                    }
                } else {
                    self::loginFailed('请完成人机验证');
                    return false;
                }
            }
        } catch (Exception $e) {
            // 配置未找到时跳过验证，不影响登录功能
        }

        /**
         * 参考 /var/Widget/User.php 中的 login 方法
         */
        if ('$P$' == substr($hash, 0, 3)) {
            $hasher = new PasswordHash(8, true);
            $hashValidate = $hasher->checkPassword($password, $hash);
        } else {
            $hashValidate = Common::hashValidate($password, $hash);
        }
        return $hashValidate;
    }

    private static function loginFailed($msg)
    {
        \Widget\Notice::alloc()->set(_t($msg), 'error');
        Options::alloc()->response->goBack();
    }

    private static function validateCapToken($token)
    {
        try {
            $config = Options::alloc()->plugin('Cap');
            $apiEndpoint = rtrim($config->apiEndpoint, '/');
            
            // 根据新的API文档，正确构造请求
            $payload = array(
                'token' => $token,
                'keepToken' => false  // 一次性使用token
            );
            
            $validateUrl = "{$apiEndpoint}/validate";
            
            // 记录调试信息
            error_log("=== Cap Token Validation Debug ===");
            error_log("Token: " . substr($token, 0, 30) . "...");
            error_log("API URL: " . $validateUrl);
            error_log("Payload: " . json_encode($payload));
            
            // 验证token格式（应该包含冒号分隔符）
            if (strpos($token, ':') === false) {
                error_log("❌ Invalid token format - missing colon separator");
                return array('success' => false, 'error' => 'Token格式无效');
            }
            
            $response = null;
            $httpCode = 0;
            
            if ($config->useCurl == 'enable' && function_exists('curl_init')) {
                $curl = curl_init();
                curl_setopt_array($curl, [
                    CURLOPT_URL => $validateUrl,
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_POST => true,
                    CURLOPT_POSTFIELDS => json_encode($payload),
                    CURLOPT_HTTPHEADER => [
                        'Content-Type: application/json',
                        'Accept: application/json',
                        'User-Agent: Cap-Typecho-Plugin/2.0'
                    ],
                    CURLOPT_TIMEOUT => 15,
                    CURLOPT_CONNECTTIMEOUT => 10,
                    CURLOPT_SSL_VERIFYPEER => false,
                    CURLOPT_SSL_VERIFYHOST => false,
                    CURLOPT_FOLLOWLOCATION => true,
                    CURLOPT_MAXREDIRS => 3
                ]);
                
                $response = curl_exec($curl);
                $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
                $curlError = curl_error($curl);
                curl_close($curl);
                
                if ($response === false) {
                    error_log("❌ cURL error: " . $curlError);
                    return array('success' => false, 'error' => 'cURL请求失败: ' . $curlError);
                }
                
                error_log("✅ cURL request completed - HTTP " . $httpCode);
                
            } else {
                // 使用 file_get_contents 作为备选
                $context = stream_context_create([
                    'http' => [
                        'method' => 'POST',
                        'header' => [
                            'Content-Type: application/json',
                            'Accept: application/json',
                            'User-Agent: Cap-Typecho-Plugin/2.0'
                        ],
                        'content' => json_encode($payload),
                        'timeout' => 15,
                        'ignore_errors' => true
                    ]
                ]);
                
                $response = @file_get_contents($validateUrl, false, $context);
                
                // 获取HTTP状态码
                if (isset($http_response_header)) {
                    $statusLine = $http_response_header[0];
                    preg_match('/HTTP\/\d\.\d\s+(\d+)/', $statusLine, $matches);
                    $httpCode = isset($matches[1]) ? (int)$matches[1] : 0;
                }
                
                if ($response === false) {
                    error_log("❌ file_get_contents request failed");
                    return array('success' => false, 'error' => '网络请求失败');
                }
                
                error_log("✅ file_get_contents request completed - HTTP " . $httpCode);
            }
            
            // 记录响应信息
            error_log("HTTP Status: " . $httpCode);
            error_log("Response length: " . strlen($response));
            error_log("Response: " . $response);
            
            // 检查HTTP状态码
            if ($httpCode >= 400) {
                error_log("❌ HTTP error: " . $httpCode);
                
                // 尝试解析错误响应
                $errorData = json_decode($response, true);
                if ($errorData && isset($errorData['error'])) {
                    return array('success' => false, 'error' => 'HTTP ' . $httpCode . ': ' . $errorData['error']);
                }
                
                return array('success' => false, 'error' => 'HTTP错误: ' . $httpCode);
            }
            
            // 检查响应是否为空
            if (empty($response)) {
                error_log("❌ Empty response");
                return array('success' => false, 'error' => '服务器无响应');
            }
            
            // 清理响应内容
            $response = trim($response);
            $response = ltrim($response, "\xEF\xBB\xBF"); // 移除UTF-8 BOM
            
            // 检查是否返回HTML错误页面
            if (stripos($response, '<html') !== false || stripos($response, '<!doctype') !== false) {
                error_log("❌ Server returned HTML page");
                return array('success' => false, 'error' => '服务器返回错误页面，请检查API端点');
            }
            
            // 解析JSON响应
            $result = json_decode($response, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                error_log("❌ JSON decode error: " . json_last_error_msg());
                error_log("Raw response: " . bin2hex(substr($response, 0, 100)));
                return array('success' => false, 'error' => 'JSON解析错误: ' . json_last_error_msg());
            }
            
            if (!is_array($result)) {
                error_log("❌ Invalid response format");
                return array('success' => false, 'error' => '响应格式无效');
            }
            
            error_log("✅ Parsed response: " . json_encode($result));
            
            // 根据API文档，成功响应应该包含 success: true
            if (isset($result['success']) && $result['success'] === true) {
                error_log("✅ Token validation successful");
                return array('success' => true);
            } else {
                error_log("❌ Token validation failed");
                $errorMsg = isset($result['error']) ? $result['error'] : '验证失败';
                return array('success' => false, 'error' => $errorMsg);
            }
            
        } catch (Exception $e) {
            error_log("❌ Exception in validateCapToken: " . $e->getMessage());
            error_log("Stack trace: " . $e->getTraceAsString());
            return array('success' => false, 'error' => '系统错误: ' . $e->getMessage());
        }
    }

    /**
     * cURL POST 请求
     */
    private static function CurlPOST($payload, $url)
    {
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/118.0.0.0 Safari/537.36');
        curl_setopt($curl, CURLOPT_HTTPHEADER, array(
            'Content-Type: application/json',
            'Accept: application/json'
        ));
        curl_setopt($curl, CURLOPT_POSTFIELDS, $payload);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($curl, CURLOPT_ENCODING, 'gzip, deflate');
        curl_setopt($curl, CURLOPT_TIMEOUT, 10);
        curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, 5);

        $response = curl_exec($curl);
        
        // 记录 cURL 错误信息
        if ($response === false) {
            $error = curl_error($curl);
            error_log("Cap Plugin Debug - cURL error: " . $error);
        }
        
        $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        error_log("Cap Plugin Debug - HTTP response code: " . $httpCode);
        
        curl_close($curl);
        return $response;
    }

    /**
     * 获取 Cap 验证结果消息
     */
    private static function getCapResultMsg($resp)
    {
        if (is_array($resp) && isset($resp['success']) && $resp['success'] === true) {
            return '验证通过';
        }
        
        if (is_array($resp) && isset($resp['error'])) {
            $error = $resp['error'];
            
            // 根据不同的错误类型返回友好的消息
            if (strpos($error, 'cURL') !== false) {
                return '网络连接失败，请稍后重试';
            } elseif (strpos($error, '网络请求失败') !== false) {
                return '网络请求失败，请检查网络连接';
            } elseif (strpos($error, '服务器响应格式错误') !== false) {
                return '验证服务异常，请稍后重试';
            } elseif (strpos($error, '服务器返回了错误页面') !== false) {
                return '验证服务配置错误，请联系管理员';
            } elseif (strpos($error, '服务器无响应') !== false) {
                return '验证服务无响应，请稍后重试';
            } else {
                return '人机验证失败：' . $error . '，请重新验证';
            }
        }
        
        return '人机验证失败，请重新验证';
    }

}