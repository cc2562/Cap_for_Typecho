# Cap for Typecho

一个为 Typecho 博客系统提供 Cap 人机验证功能的插件。

---

![Static Badge](https://img.shields.io/badge/Apache_License-V2.0-green)
![GitHub commit activity](https://img.shields.io/github/commit-activity/m/cc2562/Cap_for_Typecho)

Cap 是一个现代、轻量级的开源 SHA-256 工作量证明 CAPTCHA 替代方案。

与传统的 CAPTCHA 不同，Cap：
- 快速且不显眼
- 不使用跟踪或 cookies
- 使用工作量证明而非令人烦恼的视觉谜题
- 完全可访问且可自托管

## 功能特性

- 支持在评论和登录页面启用 Cap 验证码
- 支持自定义 Cap API 端点
- 支持亮色/暗色主题切换
- 支持 cURL 和 file_get_contents 两种请求方式
- 提供救援模式，便于故障排查
- 基于最新的 Cap API 规范

## 安装方法

1. 将 `Cap` 文件夹上传到 Typecho 的 `usr/plugins/` 目录下
2. 在 Typecho 后台的"插件管理"中启用 Cap 插件
3. 进入插件设置页面配置相关参数

## 配置说明

### 必需配置

- **Cap API 端点**: Cap 验证服务的 API 端点地址，默认：`https://captcha.gurl.eu.org/api/`
- **Cap 脚本地址**: Cap 客户端脚本的 URL 地址，默认：`https://captcha.gurl.eu.org/cap.min.js`

### 可选配置

- **启用位置**: 选择在登录页面和/或评论页面启用验证
- **主题**: 选择亮色或暗色主题
- **使用 cURL**: 推荐启用，需要 PHP cURL 扩展

## 自托管Cap
Cap支持自托管，你可以查看官方文档自行建立服务器[https://capjs.js.org/guide/server.html](https://capjs.js.org/guide/server.html)

注意本插件不支持Cap Standalone模式。自托管推荐使用Cloudflare一键部署：[https://github.com/xyTom/cap-worker](https://github.com/xyTom/cap-worker)

## 主题集成

### 评论表单集成

如果要在评论表单中显示验证码，需要在主题的评论表单中添加以下代码：

```php
<?php if (class_exists('Cap_Plugin')): ?>
    <?php Cap_Plugin::output(); ?>
<?php endif; ?>
```

通常添加在评论表单的提交按钮之前。

### 示例代码

```php
<form method="post" action="<?php $this->commentUrl() ?>" id="comment-form" role="form">
    <!-- 其他表单字段 -->
    
    <?php if (class_exists('Cap_Plugin')): ?>
        <?php Cap_Plugin::output(); ?>
    <?php endif; ?>
    
    <button type="submit" class="submit"><?php _e('提交评论'); ?></button>
</form>
```

## Cap 验证服务

本插件基于新的 Cap API 规范，使用以下端点：

### 客户端集成
- 脚本地址: `https://captcha.gurl.eu.org/api/`
- Widget 配置: `data-cap-api-endpoint="https://captcha.gurl.eu.org/cap.min.js"`

### 服务端验证
- 验证端点: `POST /api/validate`
- 请求格式:
  ```json
  {
    "token": "验证token",
    "keepToken": false
  }
  ```
- 响应格式:
  ```json
  {
    "success": true
  }
  ```


## 故障排查

### 救援模式

如果登录验证出现问题导致无法登录后台，可以：

1. 编辑 `Cap/Plugin.php` 文件
2. 将 `private static $rescueMode = false;` 改为 `private static $rescueMode = true;`
3. 这将临时跳过登录验证，允许你进入后台调整设置

### 常见问题

1. **验证码不显示**: 检查 API 端点和脚本地址是否正确
2. **验证失败**: 检查网络连接是否正常，API 服务是否可用
3. **JavaScript 错误**: 检查浏览器控制台是否有错误信息

### 调试信息

插件会在浏览器控制台输出调试信息：
- 验证完成时会显示 "Cap 验证完成" 或 "Cap 登录验证完成"
- 可以检查表单中是否正确添加了 `cap-token` 隐藏字段

## 鸣谢

- Cap 项目: https://github.com/prosopo/captcha

## 许可证

本插件基于 Apache License Version 2.0 许可证发布。

## 更新日志

### v1.0.0
- 初始版本
- 支持评论和登录验证
- 支持自定义 Cap Standalone 服务器