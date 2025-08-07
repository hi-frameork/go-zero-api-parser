# go-zero API 解析器

这是一个用于解析 go-zero `.api` 文件的工具，通过 PHP 调用 Go 可执行文件的方式实现 API 文件解析，并将结果以 JSON 格式返回，方便 PHP 进一步处理。

## 🚀 快速开始

```php
<?php
require_once 'vendor/autoload.php';
use GoZeroApiParser\ApiParser;

// 🌟 零配置启动：自动检测环境，智能选择可执行文件
$parser = new ApiParser();

// 解析 API 文件
$result = $parser->parseFile('doc/admin.api');
echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
```

**环境自动检测特性：**

- ✅ macOS ARM 芯片自动使用 `api-parser-macos-arm64`
- ✅ 无可执行文件时自动编译 `main.go`（需 Go 环境）
- ✅ 无 Go 环境时提供详细安装指导

## 🚀 特性

- ✅ 使用 go-zero 官方解析器，保证解析结果的准确性
- ✅ **智能环境检测**: 自动检测运行环境，优选 ARM 版本，支持自动编译
- ✅ 支持解析 API 语法、信息块、服务定义、路由等
- ✅ 提供友好的 PHP API 接口
- ✅ 支持错误处理和异常捕获
- ✅ 支持批量解析多个 API 文件
- ✅ 输出标准 JSON 格式，便于进一步处理
- ✅ **跨平台支持**: 自动检测 macOS ARM/Intel、Linux、Windows

## 📋 系统要求

- PHP 7.4+ 
- Go 1.19+ （可选，用于自动编译，如果有预编译可执行文件则无需 Go 环境）
- go-zero 框架依赖（仅编译时需要）

## 🛠️ 安装步骤

### 1. 克隆项目

```bash
git clone <repository-url>
cd go-zero-api-parser
```

### 2. 构建 Go 可执行文件

```bash
# 安装 Go 依赖
go mod tidy

# 构建可执行文件
go build -o api-parser main.go
```

### 3. 测试安装

```bash
# 测试 Go 程序
./api-parser doc/admin.api

# 测试 PHP 程序
php test.php
```

## 📖 使用方法

### PHP API 使用

```php
<?php

require_once 'vendor/autoload.php'; // 或 'src/ApiParser.php'

use GoZeroApiParser\ApiParser;

// 🌟 推荐：自动检测环境（无需参数）
$parser = new ApiParser();

// 手动指定可执行文件（如果需要）
// $parser = new ApiParser('./api-parser-macos-arm64');

// 检查检测结果
echo "使用的可执行文件: " . $parser->getExecutablePath() . "\n";
echo "是否自动检测: " . ($parser->isAutoDetected() ? '是' : '否') . "\n";

// 解析 API 文件
$result = $parser->parseFile('doc/admin.api');

// 获取服务信息
$services = $parser->getServices('doc/admin.api');

// 获取路由信息
$routes = $parser->getRoutes('doc/admin.api');

// 获取类型定义
$types = $parser->getTypes('doc/admin.api');
```

### 命令行使用

```bash
# 直接解析 API 文件
./api-parser doc/admin.api

# 解析结果将以 JSON 格式输出到标准输出
```

## 🔧 PHP API 参考

### ApiParser 类

#### 构造函数

```php
public function __construct(?string $executablePath = null)
```

**参数说明:**

- `$executablePath`: 可执行文件路径（可选）
  - `null`（推荐）: 自动检测环境，智能选择可执行文件
  - `string`: 手动指定可执行文件路径

**🌟 环境自动检测逻辑:**

1. **macOS ARM 优先**: 检测到 macOS ARM 芯片时，优先使用 `api-parser-macos-arm64`
2. **通用回退**: 使用通用的 `api-parser` 可执行文件  
3. **自动编译**: 如果有 Go 环境但无可执行文件，自动编译 `main.go`
4. **友好提示**: 无 Go 环境时提供详细的安装指导

#### 主要方法

##### parseFile(string $apiFilePath): array

解析指定的 API 文件并返回完整的结构化数据。

```php
$result = $parser->parseFile('doc/admin.api');
```

##### parseFileToJson(string $apiFilePath, bool $prettyPrint = true): string

解析 API 文件并返回 JSON 字符串。

```php
$json = $parser->parseFileToJson('doc/admin.api', true);
```

##### getServices(string $apiFilePath): array

获取 API 文件中的服务定义。

```php
$services = $parser->getServices('doc/admin.api');
foreach ($services as $service) {
    echo "服务名: " . $service['name'] . "\n";
    echo "路由组: " . $service['server']['group'] . "\n";
}
```

##### getRoutes(string $apiFilePath): array

获取所有路由信息（包含服务信息）。

```php
$routes = $parser->getRoutes('doc/admin.api');
foreach ($routes as $route) {
    echo $route['method'] . ' ' . $route['path'] . ' -> ' . $route['handler'] . "\n";
}
```

##### getTypes(string $apiFilePath): array

获取类型定义。

```php
$types = $parser->getTypes('doc/admin.api');
foreach ($types as $type) {
    echo "类型: " . $type['name'] . "\n";
    foreach ($type['fields'] as $field) {
        echo "  - " . $field['name'] . ": " . $field['type'] . "\n";
    }
}
```

##### getBasicInfo(string $apiFilePath): array

获取基本信息（语法版本、info 块、导入等）。

```php
$info = $parser->getBasicInfo('doc/admin.api');
echo "标题: " . $info['info']['title'] . "\n";
echo "版本: " . $info['info']['version'] . "\n";
```

##### parseMultipleFiles(array $apiFilePaths): array

批量解析多个 API 文件。

```php
$results = $parser->parseMultipleFiles([
    'doc/admin.api',
    'doc/user.api'
]);
```

## 📊 解析结果结构

解析结果是一个包含以下字段的关联数组：

```json
{
  "syntax": "v1",
  "info": {
    "title": "message服务admin接口",
    "desc": "message服务admin接口",
    "author": "",
    "date": "",
    "version": "v1"
  },
  "imports": ["system/msg.api"],
  "types": [
    {
      "name": "SaveConfigReq",
      "fields": [
        {
          "name": "id",
          "type": "int64",
          "tag": "json:\"id,optional\"",
          "comment": "记录id，编辑时必须",
          "optional": true
        }
      ]
    }
  ],
  "services": [
    {
      "name": "Message",
      "server": {
        "group": "msg",
        "prefix": "/api/v2/message/admin/system/msg",
        "auth": "",
        "middleware": [],
        "timeout": ""
      },
      "routes": [
        {
          "handler": "saveConfig",
          "method": "post",
          "path": "/save-config",
          "request_type": "SaveConfigReq",
          "response_type": "SaveConfigResp",
          "doc": {
            "summary": "新增/编辑系统消息配置",
            "description": "新增/编辑系统消息配置"
          }
        }
      ]
    }
  ]
}
```

## 🎯 实际应用场景

### 1. API 文档生成

```php
$parser = new ApiParser('./api-parser');
$services = $parser->getServices('api/user.api');

// 生成 API 文档
foreach ($services as $service) {
    echo "# " . $service['name'] . " 服务\n\n";
    foreach ($service['routes'] as $route) {
        echo "## " . $route['doc']['summary'] . "\n";
        echo "- **方法**: " . strtoupper($route['method']) . "\n";
        echo "- **路径**: " . $route['path'] . "\n";
        echo "- **处理器**: " . $route['handler'] . "\n\n";
    }
}
```

### 2. 路由注册

```php
$parser = new ApiParser('./api-parser');
$routes = $parser->getRoutes('api/user.api');

// 自动注册路由
foreach ($routes as $route) {
    $fullPath = $route['service_server']['prefix'] . $route['path'];
    registerRoute($route['method'], $fullPath, $route['handler']);
}
```

### 3. 接口测试用例生成

```php
$parser = new ApiParser('./api-parser');
$routes = $parser->getRoutes('api/user.api');

// 生成测试用例
foreach ($routes as $route) {
    generateTestCase(
        $route['method'],
        $route['path'], 
        $route['request_type'],
        $route['response_type']
    );
}
```

## ⚠️ 注意事项

1. **类型定义解析**: 当前版本在解析包含 import 语句的 API 文件时，类型定义可能不会完全解析，因为类型定义在被导入的文件中。

2. **错误处理**: 建议总是使用 try-catch 来处理可能的解析错误：

   ```php
   try {
       $result = $parser->parseFile('api/user.api');
   } catch (Exception $e) {
       echo "解析失败: " . $e->getMessage();
   }
   ```

3. **可执行文件路径**: 确保 Go 可执行文件路径正确，并且文件具有执行权限。

4. **性能考虑**: 对于大型 API 文件或频繁调用，建议考虑缓存解析结果。

## 🔍 故障排除

### 常见问题

1. **"API 解析器可执行文件不存在"**
   - 检查 Go 可执行文件是否已正确构建
   - 确认文件路径是否正确

2. **"API 解析器文件不可执行"**
   - 在 Unix/Linux 系统上运行: `chmod +x api-parser`

3. **"API 解析失败"**
   - 检查 API 文件语法是否正确
   - 确认文件路径是否存在

4. **"JSON 解析失败"**
   - 可能是 Go 程序输出了错误信息，检查 Go 程序是否正常工作

## 📁 项目结构

```text
go-zero-api-parser/
├── main.go                     # Go 解析器主程序
├── api-parser                  # 通用可执行文件
├── api-parser-macos-arm64      # macOS ARM 专用可执行文件
├── src/
│   └── ApiParser.php           # PHP 包装器类（支持自动环境检测）
├── doc/
│   ├── admin.api               # 示例 API 文件
│   └── system/
│       └── msg.api             # 导入的类型定义文件
├── test_environment.php        # 环境检测测试文件
├── example.php                 # 使用示例（需要 composer）
├── composer.json               # PHP 依赖配置
├── go.mod                     # Go 模块配置
├── ENVIRONMENT_AUTO_DETECTION.md # 环境检测功能详细说明
└── README.md                  # 本文档
```

## 🤝 贡献

欢迎提交 Issue 和 Pull Request 来改进这个项目！

## 📄 许可证

本项目采用 MIT 许可证。详见 LICENSE 文件。
