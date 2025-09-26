# Cap 插件安装指南

## 系统要求

- Typecho 1.0 或更高版本
- PHP 7.0 或更高版本
- 已部署的 Cap Standalone 服务器
- PHP cURL 扩展（推荐）

## 安装步骤

### 1. 下载插件

将整个 `Cap` 文件夹上传到你的 Typecho 安装目录的 `usr/plugins/` 文件夹中。

完整路径应该是：`/path/to/typecho/usr/plugins/Cap/`

### 2. 启用插件

1. 登录 Typecho 后台管理界面
2. 进入"控制台" → "插件管理"
3. 找到 "Cap 人机验证插件"
4. 点击"启用"按钮

### 3. 配置插件

启用插件后，点击"设置"按钮进入配置页面：

#### 必需配置项

- **Cap 服务器地址**: 输入你的 Cap Standalone 服务器地址
  - 格式：`https://your-cap-server.com`
  - 不要在末尾添加斜杠

- **Site Key**: 在 Cap Standalone 管理面板中创建站点后获得
  - 格式：类似 `d9256640cb53`

- **Secret Key**: 对应站点的密钥
  - 从 Cap Standalone 管理面板获取
  - 请妥善保管，不要泄露

#### 功能配置

- **启用位置**: 选择在哪些地方启用验证
  - 登录：在后台登录页面启用
  - 评论：在评论表单中启用（需要主题支持）

#### 外观配置

- **主题**: 选择验证码组件的外观
  - 亮色：适合浅色主题
  - 暗色：适合深色主题

#### 高级配置

- **严格模式**: 是否验证 IP 地址一致性
  - 启用：更安全，但可能影响使用代理的用户
  - 禁用：兼容性更好

- **PJAX 支持**: 为使用 PJAX 的主题提供兼容
  - 如果你的主题使用了 PJAX，建议启用

- **引入 jQuery**: 自动加载 jQuery 库
  - 如果主题已包含 jQuery，请禁用

- **使用 cURL**: 推荐的网络请求方式
  - 需要服务器支持 PHP cURL 扩展

- **cURL 证书验证**: HTTPS 证书验证
  - 生产环境建议启用

### 4. 主题集成（仅评论功能需要）

如果你启用了评论验证，需要修改主题模板：

#### 找到评论表单模板

通常在以下文件中：
- `comments.php`
- `comment-form.php` 
- `single.php`
- `post.php`

#### 添加验证码代码

在评论表单的提交按钮之前添加：

```php
<?php if (class_exists('TypechoPlugin\Cap\Plugin')): ?>
    <div class="form-group">
        <label><?php _e('人机验证'); ?></label>
        <?php TypechoPlugin\Cap\Plugin::output(); ?>
    </div>
<?php endif; ?>
```

#### 完整示例

```php
<form method="post" action="<?php $this->commentUrl() ?>" id="comment-form">
    <!-- 其他表单字段 -->
    
    <div class="form-group">
        <label for="text"><?php _e('评论内容'); ?></label>
        <textarea name="text" id="text" required><?php $this->remember('text'); ?></textarea>
    </div>
    
    <!-- 添加 Cap 验证码 -->
    <?php if (class_exists('TypechoPlugin\Cap\Plugin')): ?>
    <div class="form-group">
        <label><?php _e('人机验证'); ?></label>
        <?php TypechoPlugin\Cap\Plugin::output(); ?>
    </div>
    <?php endif; ?>
    
    <button type="submit"><?php _e('提交评论'); ?></button>
</form>
```

## Cap Standalone 服务器配置

### 部署服务器

1. 按照 Cap Standalone 官方文档部署服务器
2. 确保服务器可以从互联网访问
3. 配置 HTTPS（推荐）

### 创建站点

1. 访问 Cap Standalone 管理面板
2. 创建新站点
3. 设置允许的域名
4. 获取 Site Key 和 Secret Key

### 测试配置

在插件配置完成后：

1. 访问你的网站评论页面
2. 检查验证码是否正常显示
3. 尝试提交评论测试验证功能
4. 检查后台登录是否正常

## 故障排查

### 验证码不显示

1. 检查服务器地址是否正确
2. 检查 Site Key 是否正确
3. 检查网络连接
4. 查看浏览器控制台错误信息

### 验证失败

1. 检查 Secret Key 是否正确
2. 检查服务器时间是否同步
3. 检查网络连接
4. 尝试禁用严格模式

### 登录问题

如果启用登录验证后无法登录：

1. 编辑 `Cap/Plugin.php`
2. 将第 22 行的 `private static $rescueMode = false;` 改为 `private static $rescueMode = true;`
3. 登录后台调整设置
4. 记得改回 `false`

### 主题兼容性

1. 确保主题支持 jQuery（如果需要）
2. 检查主题是否有 CSS 冲突
3. 尝试启用 PJAX 支持（如果主题使用 PJAX）

## 安全建议

1. 定期更新 Cap Standalone 服务器
2. 使用 HTTPS 连接
3. 启用 cURL 证书验证
4. 妥善保管 Secret Key
5. 定期检查服务器日志

## 技术支持

- 插件问题：检查配置和日志
- Cap 服务器问题：参考官方文档
- 主题集成问题：联系主题作者

## 卸载插件

1. 在后台禁用插件
2. 删除 `usr/plugins/Cap/` 文件夹
3. 从主题中移除相关代码（如果有）