# go-zero API 解析器

这是一个用于解析 go-zero `.api` 文件的 PHP 库，通过调用 Go 可执行文件的方式实现 API 文件解析，并将结果以 JSON 格式返回，方便 PHP 应用进一步处理。

## 🚀 快速开始

```php
<?php
require_once 'vendor/autoload.php';
use GoZeroApiParser\ApiParser;

// 🌟 零配置启动：自动检测环境，智能选择可执行文件
$parser = new ApiParser();

// 解析 API 文件
$result = $parser->parseFile('doc/user.api');
echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
```

**环境自动检测特性：**

- ✅ macOS ARM 芯片自动使用 `api-parser-macos-arm64`
- ✅ 无可执行文件时自动编译 `go/main.go`（需 Go 环境）
- ✅ 无 Go 环境时提供详细安装指导
- ✅ 支持 Composer 自动编译脚本

## 🚀 特性

- ✅ 使用 go-zero 官方解析器，保证解析结果的准确性
- ✅ **智能环境检测**: 自动检测运行环境，优选 ARM 版本，支持自动编译
- ✅ 支持解析 API 语法、信息块、服务定义、路由等
- ✅ 提供友好的 PHP API 接口
- ✅ 支持错误处理和异常捕获
- ✅ 支持批量解析多个 API 文件
- ✅ 输出标准 JSON 格式，便于进一步处理
- ✅ **跨平台支持**: 自动检测 macOS ARM/Intel、Linux、Windows
- ✅ **完善的测试覆盖**: 包含 PHPUnit 测试套件

## 📋 系统要求

- PHP 8.0+
- Go 1.21+ （可选，用于自动编译，如果有预编译可执行文件则无需 Go 环境）
- go-zero 框架依赖（仅编译时需要）

## 🛠️ 安装步骤

### 1. 克隆项目

```bash
git clone <repository-url>
cd go-zero-api-parser
```

### 2. 安装 PHP 依赖

```bash
# 使用 Composer 安装依赖
composer install
```

### 3. 构建 Go 可执行文件（可选）

```bash
# 进入 Go 代码目录
cd go

# 安装 Go 依赖
go mod tidy

# 构建可执行文件
go build -o ../api-parser-compiled main.go

# 或者使用 Composer 脚本
cd ..
composer run build
```

### 4. 测试安装

```bash
# 测试 Go 程序
./api-parser-macos-arm64 doc/user.api

# 测试 PHP 程序
php example.php

# 运行测试套件
composer test
```

## 📖 使用方法

### PHP API 使用

```php
<?php

require_once 'vendor/autoload.php';

use GoZeroApiParser\ApiParser;

// 🌟 推荐：自动检测环境（无需参数）
$parser = new ApiParser();

// 检查检测结果
echo "使用的可执行文件: " . $parser->getExecutor()->getExecutablePath() . "\n";
echo "是否自动检测: " . ($parser->getExecutor()->isAutoDetected() ? '是' : '否') . "\n";

// 解析 API 文件
$result = $parser->parseFile('doc/user.api');

// 获取基本信息
$info = $parser->getInfo('doc/user.api');

// 获取服务信息
$service = $parser->getService('doc/user.api');

// 获取路由组信息
$groups = $parser->getGroups('doc/user.api');

// 获取类型定义
$types = $parser->getTypes('doc/user.api');
```

### 命令行使用

```bash
# 直接解析 API 文件
./api-parser-macos-arm64 doc/user.api

# 或使用编译的可执行文件
./api-parser-compiled doc/user.api

# 解析结果将以 JSON 格式输出到标准输出
```

## 🔧 PHP API 参考

### ApiParser 类

#### 构造函数

```php
public function __construct(?Executor $executor = null)
```

**参数说明:**

- `$executor`: 执行器实例（可选）
  - `null`（推荐）: 自动创建 Executor 实例，使用环境自动检测
  - `Executor`: 手动指定执行器实例

**🌟 环境自动检测逻辑（通过 Executor 类）:**

1. **macOS ARM 优先**: 检测到 macOS ARM 芯片时，优先使用 `api-parser-macos-arm64`
2. **编译版本回退**: 使用 `api-parser-compiled` 可执行文件  
3. **自动编译**: 如果有 Go 环境但无可执行文件，自动编译 `go/main.go`
4. **友好提示**: 无 Go 环境时提供详细的安装指导

#### 主要方法

##### parseFile(string $apiFilePath): array

解析指定的 API 文件并返回完整的结构化数据。

```php
$result = $parser->parseFile('doc/user.api');
```

##### parseFileToJson(string $apiFilePath, bool $prettyPrint = true): string

解析 API 文件并返回 JSON 字符串。

```php
$json = $parser->parseFileToJson('doc/user.api', true);
```

##### getInfo(string $apiFilePath): array

获取基本信息（info 块）。

```php
$info = $parser->getInfo('doc/user.api');
echo "标题: " . $info['title'] . "\n";
echo "版本: " . $info['version'] . "\n";
```

##### getService(string $apiFilePath): array

获取服务定义。

```php
$service = $parser->getService('doc/user.api');
echo "服务名: " . $service['Name'] . "\n";
```

##### getGroups(string $apiFilePath): array

获取路由组信息。

```php
$groups = $parser->getGroups('doc/user.api');
foreach ($groups as $group) {
    echo "路由组: " . $group['Annotation']['Properties']['group'] . "\n";
    echo "路径前缀: " . $group['Annotation']['Properties']['prefix'] . "\n";
    foreach ($group['Routes'] as $route) {
        echo "  " . $route['Method'] . " " . $route['Path'] . " -> " . $route['Handler'] . "\n";
    }
}
```

##### getTypes(string $apiFilePath): array

获取类型定义。

```php
$types = $parser->getTypes('doc/user.api');
foreach ($types as $type) {
    echo "类型: " . $type['RawName'] . "\n";
    foreach ($type['Members'] as $field) {
        echo "  - " . $field['Name'] . ": " . $field['Type']['RawName'] . "\n";
    }
}
```

##### parseMultipleFiles(array $apiFilePaths): array

批量解析多个 API 文件。

```php
$results = $parser->parseMultipleFiles([
    'doc/user.api',
    'doc/product.api',
    'doc/order.api'
]);
```

## 📊 解析结果结构

解析结果是一个包含以下字段的关联数组：

```json
{
  "Syntax": {
    "Syntax": "v1"
  },
  "Info": {
    "Title": "用户服务接口",
    "Desc": "用户管理相关的API接口",
    "Author": "开发团队",
    "Email": "dev@example.com",
    "Version": "v1.0"
  },
  "Imports": [],
  "Types": [
    {
      "RawName": "User",
      "Members": [
        {
          "Name": "Id",
          "Type": {
            "RawName": "int64"
          },
          "Tag": "json:\"id\"",
          "Comment": "用户ID"
        },
        {
          "Name": "Username",
          "Type": {
            "RawName": "string"
          },
          "Tag": "json:\"username\"",
          "Comment": "用户名"
        }
      ]
    }
  ],
  "Service": {
    "Name": "UserService",
    "Groups": [
      {
        "Annotation": {
          "Properties": {
            "group": "user",
            "prefix": "/api/v1/users",
            "jwt": "Auth"
          }
        },
        "Routes": [
          {
            "Method": "post",
            "Path": "/register",
            "Handler": "register",
            "RequestType": {
              "RawName": "RegisterReq"
            },
            "ResponseType": {
              "RawName": "RegisterResp"
            },
            "Doc": {
              "Properties": {
                "summary": "用户注册",
                "description": "创建新用户账户"
              }
            }
          }
        ]
      }
    ]
  }
}
```

## 🎯 实际应用场景

### 1. API 文档生成

```php
$parser = new ApiParser();
$service = $parser->getService('doc/user.api');
$groups = $parser->getGroups('doc/user.api');

// 生成 API 文档
echo "# " . $service['Name'] . " 服务\n\n";
foreach ($groups as $group) {
    $prefix = $group['Annotation']['Properties']['prefix'] ?? '';
    foreach ($group['Routes'] as $route) {
        echo "## " . ($route['Doc']['Properties']['summary'] ?? '') . "\n";
        echo "- **方法**: " . strtoupper($route['Method']) . "\n";
        echo "- **路径**: " . $prefix . $route['Path'] . "\n";
        echo "- **处理器**: " . $route['Handler'] . "\n\n";
    }
}
```

### 2. 路由注册

```php
$parser = new ApiParser();
$groups = $parser->getGroups('doc/user.api');

// 自动注册路由
foreach ($groups as $group) {
    $prefix = $group['Annotation']['Properties']['prefix'] ?? '';
    foreach ($group['Routes'] as $route) {
        $fullPath = $prefix . $route['Path'];
        registerRoute($route['Method'], $fullPath, $route['Handler']);
    }
}
```

### 3. 接口测试用例生成

```php
$parser = new ApiParser();
$groups = $parser->getGroups('doc/user.api');

// 生成测试用例
foreach ($groups as $group) {
    foreach ($group['Routes'] as $route) {
        generateTestCase(
            $route['Method'],
            $route['Path'], 
            $route['RequestType']['RawName'] ?? null,
            $route['ResponseType']['RawName'] ?? null
        );
    }
}
```

## ⚠️ 注意事项

1. **类型定义解析**: 当前版本在解析包含 import 语句的 API 文件时，类型定义可能不会完全解析，因为类型定义在被导入的文件中。

2. **错误处理**: 建议总是使用 try-catch 来处理可能的解析错误：

   ```php
   try {
       $result = $parser->parseFile('doc/user.api');
   } catch (Exception $e) {
       echo "解析失败: " . $e->getMessage();
   }
   ```

3. **可执行文件路径**: 使用自动检测时无需关心，手动指定时确保 Go 可执行文件路径正确，并且文件具有执行权限。

4. **性能考虑**: 对于大型 API 文件或频繁调用，建议考虑缓存解析结果。

## 🧪 测试

项目包含完整的 PHPUnit 测试套件：

```bash
# 运行所有测试
composer test

# 查看测试覆盖率（如果安装了 xdebug）
vendor/bin/phpunit --coverage-text

# 运行特定测试类
vendor/bin/phpunit tests/Executor/ExecutorTest.php
```

## 🔍 故障排除

### 常见问题

1. **"API 解析器可执行文件不存在"**
   - 检查是否已运行 `composer install`
   - 尝试运行 `composer run build` 编译 Go 程序
   - 确认 Go 环境是否正确安装

2. **"API 解析器文件不可执行"**
   - 在 Unix/Linux 系统上运行: `chmod +x api-parser-macos-arm64`

3. **"API 解析失败"**
   - 检查 API 文件语法是否正确
   - 确认文件路径是否存在
   - 使用命令行直接测试 Go 程序: `./api-parser-macos-arm64 doc/user.api`

4. **"JSON 解析失败"**
   - 可能是 Go 程序输出了错误信息，检查 Go 程序是否正常工作
   - 查看原始输出内容以诊断问题

## 📁 项目结构

```text
go-zero-api-parser/
├── api-parser-macos-arm64      # macOS ARM 专用预编译可执行文件
├── composer.json               # PHP 依赖配置和脚本
├── composer.lock               # 锁定的依赖版本
├── src/                        # PHP 源代码
│   ├── ApiParser.php           # API 解析器主类
│   └── Executor.php            # Go 程序执行器（支持环境自动检测）
├── go/                         # Go 源代码
│   ├── main.go                 # Go 解析器主程序
│   ├── go.mod                  # Go 模块配置
│   └── go.sum                  # Go 依赖锁定文件
├── doc/                        # 示例 API 文件
│   ├── user.api                # 用户服务 API 定义
│   ├── product.api             # 产品服务 API 定义
│   ├── order.api               # 订单服务 API 定义
│   ├── common.api              # 通用 API 定义
│   └── simple.api              # 简单示例 API
├── tests/                      # PHPUnit 测试套件
│   ├── bootstrap.php           # 测试引导文件
│   └── Executor/               # 执行器相关测试
│       ├── ExecutorTest.php    # 基本执行器测试
│       ├── ExecutorEnvironmentTest.php # 环境检测测试
│       └── ...                 # 其他测试文件
├── example.php                 # 使用示例
├── test_api_files.php          # API 文件测试脚本
├── phpunit.xml                 # PHPUnit 配置文件
└── README.md                   # 本文档
```

## 🤝 贡献

欢迎提交 Issue 和 Pull Request 来改进这个项目！

## 📄 许可证

本项目采用 MIT 许可证。详见 LICENSE 文件。
