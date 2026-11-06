# Changelog

## 1.0.1 - 2026-11-06

### 功能

- 新增阿里云 OSS 上传封装：`AliyunProvider::oss()`，支持服务端上传、文件上传、删除、存在性、URL 与 PostObject 直传签名
- 新增七牛云 OSS 上传封装：`QiniuProvider::oss()`，支持服务端上传、文件上传、删除、存在性、URL、私有空间下载签名与上传凭证
- `toolkit:doctor` 增加 `aliyuncs/oss-sdk-php`、`qiniu/php-sdk` 可选依赖检查

### 说明

- `aliyuncs/oss-sdk-php`、`qiniu/php-sdk` 为可选依赖，缺失时抛出明确的安装提示

## 1.0.0

首个公开发布版本。

### 功能

- 脚手架命令：`builder:laravel`、`builder:package`、`builder:interface`、`toolkit:doctor`
- 字段定义 `--fields`，自动生成迁移字段、模型 cast、请求校验规则与属性名称
- `builder:package --index/--unique/--controller/--request/--route`
- 数据仓库条件 DSL：比较、模糊、区间、JSON、字段比较、原生 SQL（参数绑定）
- 统一响应结构：`state/code/msg/data/extras`，可选 `locale/consuming/request_id`
- 业务异常 `BusinessException` 与统一 JSON 异常渲染
- 中间件：请求 ID、CORS 预检、异常 JSON、`X-Response-Time`
- 微信（公众号 / 小程序）、抖音小程序原生 HTTP 封装与 access_token 缓存
- 阿里云短信封装，可选 EasySms 实现
- 头像、图片水印与切图、二维码、Storage 文件操作、DFA 敏感词过滤
- PHPUnit 单元测试与 GitHub Actions

### 兼容性

- PHP >= 8.1
- Laravel 9.x / 10.x / 11.x / 12.x
- 命名空间 `Readfly\Laravel`
- 配置文件 `config/toolkit.php`

### 说明

- `intervention/image`、`simple-qrcode`、`php-dfa-sensitive`、`easy-sms` 为可选依赖
- 脚手架生成的迁移默认使用匿名类写法（Laravel 8+）
- `ServiceModule::getResponse()` 返回 `ResponsePayload` 对象