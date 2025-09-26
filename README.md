# Cap for Typecho

一个为 Typecho 博客系统提供 Cap 人机验证功能的插件。

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
- **Cap 脚本地址**: Cap 客户端脚本的 URL 地址，默认：`https://cdn.jsdmirror.com/gh/prosopo/captcha@latest/cap.min.js`（已优化为国内可访问的 CDN）

### 可选配置

- **启用位置**: 选择在登录页面和/或评论页面启用验证
- **主题**: 选择亮色或暗色主题
- **使用 cURL**: 推荐启用，需要 PHP cURL 扩展

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
- 脚本地址: `https://cdn.jsdmirror.com/gh/prosopo/captcha@latest/cap.min.js`（国内优化 CDN）
- Widget 配置: `data-cap-api-endpoint="https://captcha.gurl.eu.org/api/"`

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

## 工作原理

1. **客户端**: 用户完成验证后，JavaScript 会生成一个 token
2. **表单提交**: token 作为隐藏字段 `cap-token` 随表单一起提交
3. **服务端验证**: 插件调用 `/api/validate` 端点验证 token
4. **结果处理**: 根据验证结果决定是否允许操作

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

## 技术支持

- Cap 项目: https://github.com/prosopo/captcha
- Typecho 官网: https://typecho.org

## 许可证

本插件基于 AGPL-3.0 许可证发布。

## 更新日志

### v2.0.0
- 适配新的 Cap API 规范
- 移除对 site_key 和 secret_key 的依赖
- 使用 token 验证机制
- 简化配置选项
- 改进客户端集成方式

### v1.0.0
- 初始版本
- 支持评论和登录验证
- 支持自定义 Cap Standalone 服务器
- 支持主题切换和各种配置选项