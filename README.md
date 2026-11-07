# Readfly Laravel Toolkit

面向 Laravel 9 / 10 / 11 / 12 的辅助开发工具集：脚手架、数据仓库、统一响应、异常处理与三方集成。

命名空间为 `Readfly\Laravel`，核心运行时依赖 Illuminate 组件与 `ramsey/uuid`，三方 SDK 按需引入。

## 特性

- 一条命令生成 Model / Repository / Service / Hook / Cacher / Controller / Request / Migration / Route
- 支持 `--fields` 声明字段，自动生成迁移字段、模型 cast 与请求校验规则
- 数据仓库全部使用参数绑定，修复原版 `like` 与排序中的 SQL 拼接风险
- 统一响应结构（`state/code/msg/data/extras`），可选 `locale/consuming/request_id`
- 真实可用的全局中间件：请求 ID、CORS 预检、异常统一 JSON 渲染、响应耗时
- 微信（公众号 / 小程序）、抖音小程序、阿里云短信统一封装，可选依赖缺失时给出明确提示
- 阿里云 OSS / 七牛云 OSS 上传统一封装：服务端上传、文件上传、删除、存在性、URL 与前端直传签名
- 兼容 Laravel 9-12 的自动发现（package discovery）与手动注册

## 环境要求

| 依赖 | 版本 |
|---|---|
| PHP | >= 8.1 |
| Laravel | 9.x / 10.x / 11.x / 12.x |
| Composer | 2.x |

## 安装

包名 `readfly/laravel`：

```bash
composer require readfly/laravel
```

Laravel 会自动发现服务提供者。若关闭了自动发现，手动注册：

```php
// config/app.php（Laravel 9/10）
'providers' => [
    Readfly\Laravel\LaravelToolkitServiceProvider::class,
],

// bootstrap/providers.php（Laravel 11+）
return [
    Readfly\Laravel\LaravelToolkitServiceProvider::class,
];
```

发布配置：

```bash
php artisan vendor:publish --tag=toolkit-config
```

初始化（生成签名 / AES 密钥、应用版本号，创建 storage 软链）：

```bash
php artisan builder:laravel --domain=api.example.com
```

## 注册中间件

Laravel 10：

```php
// app/Http/Kernel.php
protected $middleware = [
    // ...
    \Readfly\Laravel\Http\Middleware\BasicLaravelMiddleware::class,
];
```

Laravel 11+：

```php
// bootstrap/app.php
->withMiddleware(function (Middleware $middleware) {
    $middleware->append(\Readfly\Laravel\Http\Middleware\BasicLaravelMiddleware::class);
})
```

中间件行为由 `config/toolkit.php` 的 `middleware` 节点控制。

## 命令

### builder:package

```bash
php artisan builder:package users \
  --desc="用户信息表" \
  --dictionary=api/v1 \
  --fields="name:string(64):用户名称;email:?email:邮箱;age:int:年龄=0;meta:?json:扩展信息" \
  --unique=email \
  --migration --cache --controller --request --route
```

常用覆盖项：

```bash
--class=UserProfile          # 覆盖根据表名推导的类名，--model 为等价别名
--fields-file=fields.json    # 从文本或 JSON 文件读取字段定义
--id=uuid                    # 主键类型：id / uuid / ulid
--collation=utf8mb4_unicode_ci # 自定义表排序规则
--no-timestamps              # 不生成 created_at / updated_at
--soft-deletes               # 生成 deleted_at 并启用 SoftDeletes
--register-route             # 自动把路由片段 require 到 routes/api.php
--dry-run                    # 只预览文件，不写入
--new-migration              # 已存在同名迁移时仍创建新迁移
--middleware="auth:sanctum|throttle:60,1" # 路由中间件，多个用 | 分隔
--prefix=api/v2/users         # 覆盖路由 URI 前缀
--route-name=api.v2.users     # 覆盖路由名称前缀
```

索引和约束支持：

```bash
--index="email+tenant_id,name"
--unique="tenant_id+email"
--fulltext="title+content"
--index-name="email+tenant_id:users_email_tenant_index"
--foreign="user_id:users,id:cascade:restrict"
```

`--foreign` 格式为 `字段:目标表,目标列[:onDelete][:onUpdate]`，多个外键用 `;` 分隔。外键字段必须同时出现在 `--fields` 中；生成 Model 时会自动补充基础 `belongsTo` 关联（默认按当前 dictionary 推导关联模型，跨目录关联请手动调整）。全文索引和外键约束依赖数据库支持，生成后请按目标数据库验证。

生成：

```
app/Models/Api/V1/User.php
app/Repository/Api/V1/UserRepository.php
app/Services/Api/V1/UserService.php
app/Hooks/Api/V1/UserHook.php
app/Caches/Api/V1/UserCacher.php
app/Http/Controllers/Api/V1/UserController.php
app/Http/Requests/Api/V1/UserRequest.php
database/migrations/2026_11_06_000000_create_api_v1_user_table.php
routes/generated/api/v1/users.php
```

Migration 默认生成在 `database/migrations` 根目录，可直接执行 `php artisan migrate`。如果已存在同名迁移，命令会默认跳过；需要保留历史迁移并追加新版本时使用 `--new-migration`。

路由片段不会自动注册；使用 `--register-route` 可自动追加到 `routes/api.php`，否则按提示手动引入。路由文件名、URI 前缀和路由名都会包含 dictionary，避免不同目录或 package/interface 之间互相覆盖。

生成后的 Controller / Service / Repository / Hook / Cacher 已按基本 CRUD 链路串联：Controller 调用 Service，Service 调用 Repository，写操作执行 Hook 并在配置缓存时刷新 Cacher。外键会同时生成数据库约束和基础 `belongsTo` 关联。生成的业务代码仍是基础版本，复杂查询、权限和业务规则需要按项目补充。

覆盖已存在文件时会自动生成 `.bak` 备份；`--dry-run` 适合先检查生成计划。

可选生成项默认值来自 `config/toolkit.php` 的 `default_builder`，如需在非交互环境关闭某项，可使用 `--no-migration`、`--no-cache`、`--no-controller`、`--no-request`。

### 字段语法

```
字段名:类型(参数):注释:默认值:是否可空
字段名:类型(参数):注释=默认值
```

示例：

| 定义 | 生成 |
|---|---|
| `name:string(64):名称` | `$table->string('name', 64)->comment('名称')` |
| `age:int:年龄=0` | `$table->integer('age')->default(0)` |
| `meta:?json:扩展` | `$table->json('meta')->nullable()` |
| `price:decimal(10,2):价格` | `$table->decimal('price', 10, 2)`，cast `decimal:2` |
| `status:tinyint:状态=1` | `$table->tinyInteger('status')->default(1)` |
| `status:enum(Active,Pending):状态` | `$table->enum('status', ['Active', 'Pending'])`，校验 `Rule::in(['Active', 'Pending'])` |
| `path:string(64):路径:"C:\\"` | 引号内的冒号和反斜杠会按字面值处理 |

支持类型：`string char text mediumText longText int bigint tinyint smallint mediumint decimal float double bool json jsonb date datetime timestamp time year uuid enum binary email url`。

注释或默认值里包含冒号、等号、反斜杠时，建议使用双引号包裹对应片段；字段定义支持 `;`、`|` 或换行分隔，`--fields-file` 更适合较长的字段列表。

可选的第六段是自定义校验规则，多个规则用 `|` 分隔，并建议用双引号包裹：

```bash
--fields='email:string(64):邮箱::false:"required|email|unique:users,email"'
```

`--fields-file` 也支持 JSON：

```json
[
  {"name": "name", "type": "string(64)", "comment": "名称"},
  {"name": "age", "type": "int", "comment": "年龄", "default": 0, "rules": ["integer", "min:1"]}
]
```

### builder:interface

```bash
php artisan builder:interface orders \
  --desc="订单表" --dictionary=api/v1 \
  --fields="order_no:string(32):订单号;amount:decimal(10,2):金额" \
  --request --route
```

`builder:interface` 同样支持 `--class`、`--model`、`--fields-file`、`--middleware`、`--prefix`、`--route-name`、`--register-route` 和 `--dry-run`。

生成 `Interfaces/Api/V1/Services`、`Controllers`、`Hooks` 三件套，可选 Request 与路由片段。生成路由位于 `routes/generated/api/v1/orders.interface.php`，路由名会追加 `.interface` 后缀，避免与 `builder:package` 生成的路由冲突。

`builder:package` 的 store/update Controller 方法会自动注入生成的 Request，其他读取/删除方法使用普通 Request，避免 GET/DELETE 被字段必填规则误拦截。`builder:interface` 的 information 接口使用普通 Request；生成的 Request 类仍可在业务扩展中使用。

### toolkit:doctor

```bash
php artisan toolkit:doctor
```

检查 PHP 版本、扩展、配置发布、storage 权限、头像资源与可选依赖。可选依赖缺失只影响对应功能，不会让命令失败。

## 数据仓库

```php
use App\Repository\Api\V1\UserRepository;

$repository = new UserRepository();

$repository->insertGetId(['name' => '张三', 'age' => 18]);

$row = $repository->row(['id' => 1]);
$list = $repository->get(['age' => ['>=', 18]], ['name', 'age'], [], ['age' => 'desc']);
$page = $repository->lists(['name' => ['like', '张 三']], ['*'], [], ['id' => 'desc'], '', 1, 20);
```

### 条件 DSL

| 条件 | 说明 |
|---|---|
| `['field' => 'value']` | 等值 |
| `['field' => ['>', 10]]` | 比较 / `!=` / `>=` / `<` / `<=` |
| `['field' => ['like', '关键字']]` | 忽略空格模糊匹配；`name\|email` 多字段 OR |
| `['field' => ['in', [1,2]]]` | `not-in` / `between` / `not-between` / `null` / `not-null` |
| `['field' => ['date', '2026-01-01']]` | `month` / `day` / `year` / `time` |
| `['field' => ['json-contains', ['a']]]` | JSON 查询 |
| `['field' => ['column', 'other_field']]` | 字段比较 |
| `['field' => ['raw', ['sql = ?', [1]]]]` | 原生 SQL（必须使用绑定） |
| `['or-age' => ['>', 10]]` | 任意规则加 `or-` 前缀 |

排序：

```php
['id' => 'desc']
['id' => [ModelModule::FIELD_ORDER_BY, [3, 1, 2]]]
[ModelModule::RANDOM_ORDER_BY => []]
```

## 统一响应

```php
return responseSuccess(['id' => 1], '创建成功');
return responseError(Code::VALIDATE_FAILED, [], '参数错误', ['validations' => $errors]);

$service->information($request);
return responseService($service);
```

响应结构：

```json
{
  "state": true,
  "code": 0,
  "msg": "创建成功",
  "data": {"id": 1},
  "extras": [],
  "locale": "zh_CN",
  "consuming": "1.23ms",
  "request_id": "..."
}
```

业务异常：

```php
throw BusinessException::make(Code::FORBIDDEN, '无权操作');
```

中间件会将其渲染为统一 JSON。

## 三方集成

```php
use Readfly\Laravel\Providers\WechatProvider;
use Readfly\Laravel\Providers\AliyunProvider;
use Readfly\Laravel\Providers\QiniuProvider;

WechatProvider::officeAccount()->sendTemplate($uuid, $openId, $data, 'order_paid');
WechatProvider::miniProgram()->code2Session($code);
AliyunProvider::sms()->send('13800000000', ['code' => '123456'], 'authenticate');
```

### 阿里云 OSS

```php
use Readfly\Laravel\Providers\AliyunProvider;

$oss = AliyunProvider::oss();

$oss->put('uploads/a.png', $content, ['content_type' => 'image/png']);
$oss->putFile('uploads/a.png', '/tmp/a.png');
$oss->exists('uploads/a.png');
$oss->url('uploads/a.png');
$oss->delete('uploads/a.png');

// 前端 PostObject 直传凭证
$policy = $oss->postPolicy('uploads/', 3600);
// ['host' => ..., 'access_key_id' => ..., 'policy' => ..., 'signature' => ..., 'dir' => ..., 'expire_at' => ...]
```

配置位于 `config/project.php` 的 `3rd_passports.aliyun.oss`，`visibility` 为 `private` 时 `url()` 自动生成带签名的临时地址。

### 七牛云 OSS

```php
use Readfly\Laravel\Providers\QiniuProvider;

$oss = QiniuProvider::oss();

$oss->put('uploads/a.png', $content);
$oss->putFile('uploads/a.png', '/tmp/a.png');
$oss->exists('uploads/a.png');
$oss->url('uploads/a.png');
$oss->delete('uploads/a.png');

// 前端直传凭证
$token = $oss->uploadToken('uploads/a.png', 3600);
```

配置位于 `config/project.php` 的 `3rd_passports.qiniu.default`，`visibility` 为 `private` 时 `url()` 自动生成私有空间下载地址。

可选依赖（仅在调用对应功能时需要）：

```bash
composer require intervention/image simplesoftwareio/simple-qrcode lustre/php-dfa-sensitive overtrue/easy-sms aliyuncs/oss-sdk-php qiniu/php-sdk
```

## 设计说明

- 配置统一放在 `config/toolkit.php`，支持 Laravel 9-12 自动发现
- 数据仓库条件全部使用参数绑定，字段名经过白名单校验
- 事务、缓存与响应对象均使用模型/框架原生连接
- 可选依赖缺失时抛出明确提示，不影响核心功能安装
- 脚手架直接生成迁移文件，不依赖解析 `artisan` 输出
- 模板可通过 `vendor:publish --tag=toolkit-stubs` 发布到 `resources/toolkit/templates`，生成命令会优先使用已发布模板；也可通过 `toolkit.templates_path` 显式指定。升级后如果已有旧模板，需要重新发布或手动同步
- 所有 CLI 输入在写入代码前都会校验；字典、索引、字段名不会直接拼接成非法 PHP

## 测试

```bash
composer install
composer test
composer render-check
composer analyse
```

测试覆盖数据仓库条件 DSL、字段定义、模板渲染、响应对象与 PHPDoc 解析，并包含 `builder:package` / `builder:interface` 的命令级测试。命令测试会实际生成脚手架并对每个文件执行 `php -l`。`composer analyse` 使用 PHPStan 对核心源码做静态分析。

## 许可证

MIT，详见 [LICENSE](LICENSE)。