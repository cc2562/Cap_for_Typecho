<?php
/**
 * Cap 插件配置示例
 * 
 * 本文件展示了如何配置 Cap 插件以适配新的 API 规范
 */

// 基本配置示例（使用国内可访问的 CDN）
$basicConfig = [
    'apiEndpoint' => 'https://captcha.gurl.eu.org/api/',
    'scriptUrl' => 'https://cdn.jsdmirror.com/gh/prosopo/captcha@latest/cap.min.js',
    'theme' => 'light',
    'enableActions' => ['comment', 'login'],
    'useCurl' => 'enable'
];

// 使用本地文件配置示例
$localConfig = [
    'apiEndpoint' => 'https://captcha.gurl.eu.org/api/',
    'scriptUrl' => './usr/plugins/Cap/cap.min.js',
    'theme' => 'light',
    'enableActions' => ['comment', 'login'],
    'useCurl' => 'enable'
];

// 自定义服务器配置示例
$customServerConfig = [
    'apiEndpoint' => 'https://your-cap-server.com/api/',
    'scriptUrl' => 'https://your-cap-server.com/cap.min.js',
    'theme' => 'dark',
    'enableActions' => ['comment'],
    'useCurl' => 'enable'
];

// 仅登录验证配置示例
$loginOnlyConfig = [
    'apiEndpoint' => 'https://captcha.gurl.eu.org/api/',
    'scriptUrl' => 'https://cdn.jsdmirror.com/gh/prosopo/captcha@latest/cap.min.js',
    'theme' => 'light',
    'enableActions' => ['login'],
    'useCurl' => 'enable'
];

/**
 * 配置说明：
 * 
 * apiEndpoint: Cap API 端点地址，必须以 / 结尾
 * scriptUrl: Cap 客户端脚本地址
 * theme: 主题样式，可选 'light' 或 'dark'
 * enableActions: 启用验证的位置，可选 'comment' 和/或 'login'
 * useCurl: 是否使用 cURL 发送请求，推荐启用
 */

/**
 * 新 API 与旧 API 的主要区别：
 * 
 * 旧 API (v1.x):
 * - 需要 serverUrl, siteKey, secretKey
 * - 使用 /<site_key>/siteverify 端点
 * - 客户端脚本地址为 /<site_key>/
 * 
 * 新 API (v2.x):
 * - 只需要 apiEndpoint 和 scriptUrl
 * - 使用 /api/validate 端点
 * - 统一的客户端脚本地址
 * - 基于 token 的验证机制
 */

/**
 * 迁移指南：
 * 
 * 如果你正在从旧版本升级：
 * 1. 备份现有配置
 * 2. 重新配置插件设置
 * 3. 测试验证功能
 * 4. 如有问题，启用救援模式进行调试
 */
?>