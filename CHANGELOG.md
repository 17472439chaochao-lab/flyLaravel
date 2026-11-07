# Changelog

## 1.0.3 - 2026-11-07

### 修复

- 修复 `RepositoryModule` 对 Carbon / `DateTimeInterface` 值进行 JSON 编码，导致模型 `datetime` cast 解析失败的问题
- 修复 Laravel 12 `FormRequest` 校验异常未进入统一响应结构的问题，改由 ServiceProvider 注册异常渲染器
- 完善 `builder:laravel` 配置初始化与运行时依赖声明

### 功能

- 新增可选 AES-256-GCM payload 加密传输
- 新增 `PayloadCipher` 加解密器与 `EncryptedPayloadMiddleware`
- 支持 GET 查询参数、JSON 请求体、JSON 响应加密
- 前端可使用 Web Crypto API 对接，示例见本地 `website/` 测试项目

### 工程化

- 增加 PayloadCipher 回归测试
- 根包测试增至 77 个测试、360 个断言
- 本地 website 测试通过：5 个 Feature 测试、41 个断言

## 1.0.2 - 2026-11-07

### 修复

- 修复 `builder:package` 缺少 `model_class` 导致 Model、Repository、Migration 生成非法 PHP 的问题
- 修复 `builder:interface` 使用点号拼接命名空间的问题
- 生成 Controller 时自动注入对应 Request，确保请求校验规则生效
- 路由文件、URI 和路由名纳入 dictionary，避免不同目录及 package/interface 互相覆盖
- 迁移文件改为生成到 `database/migrations` 根目录
- 修复 `enum` 单值生成、枚举值大小写、默认值序列化和引号/反斜杠转义问题
- 增加 dictionary、索引字段、数据库连接、字符集、引擎等输入校验
- 模板渲染后检测未替换占位符；生成前预检查全部模板，避免半成品文件
- 发布到 `resources/toolkit/templates` 的自定义模板现在会被生成命令自动使用
- `builder:package` 增加 `--no-migration`、`--no-cache`、`--no-controller`、`--no-request`
- `toolkit:doctor` 区分必需检查与可选依赖，可选依赖缺失不再导致命令失败
- `builder:laravel` 读取配置失败时不再清空配置文件，`--force` 会先备份原配置
- 新增 Controller → Service → Repository 的基础 CRUD 串联，写操作接入 Hook 与可选 Cacher
- 新增 `--class` / `--model` 类名覆盖、`--fields-file` 字段文件、`--dry-run` 预览
- 新增 `--id=id|uuid|ulid`、`--collation`、`--no-timestamps`、`--soft-deletes`
- 索引支持 `email+tenant_id` 联合索引语法
- 新增 `--register-route` 自动注册路由、`--new-migration` 允许追加重复表迁移
- 覆盖写入时自动生成 `.bak` 备份；重复 Migration 默认跳过
- enum 校验改为 `Rule::in(...)`，修复引号内 `=` 被误判为默认值
- Request 支持 update 场景去除必填约束
- 字段定义支持第六段自定义校验规则，`--fields-file` 支持 JSON
- 新增 `--fulltext` 全文索引、`--index-name` 自定义索引名和 `--foreign` 外键约束，自动生成基础 `belongsTo` 关联
- 新增 `--middleware`、`--prefix`、`--route-name` 路由配置
- 新增 PHPStan 静态分析与 `composer analyse`，补充 `illuminate/validation`、`ramsey/uuid` 运行时依赖
- 新增命令级脚手架测试，实际生成文件并执行 `php -l`

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