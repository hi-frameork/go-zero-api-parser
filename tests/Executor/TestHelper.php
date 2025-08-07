<?php

declare(strict_types=1);

namespace GoZeroApiParser\Tests\Executor;

/**
 * 测试辅助类
 * 支持 Go 1.21+ 版本的兼容性测试
 */
class TestHelper
{
    /**
     * 最低支持的 Go 版本
     */
    public const MIN_GO_VERSION = '1.21';

    /**
     * 创建临时测试目录
     */
    public static function createTempDir(string $prefix = 'executor_test_'): string
    {
        $tempDir = \sys_get_temp_dir() . '/' . $prefix . \uniqid();
        \mkdir($tempDir, 0o755, true);
        return $tempDir;
    }

    /**
     * 递归删除目录和文件
     */
    public static function recursiveDelete(string $dir): void
    {
        if (! \is_dir($dir)) {
            return;
        }

        $files = \array_diff(\scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            if (\is_dir($path)) {
                self::recursiveDelete($path);
            } else {
                \unlink($path);
            }
        }
        \rmdir($dir);
    }

    /**
     * 创建模拟的可执行文件
     */
    public static function createMockExecutable(string $path, string $output = null): void
    {
        $output ??= self::getDefaultMockOutput();
        $scriptContent = "#!/bin/bash\necho '{$output}'";
        \file_put_contents($path, $scriptContent);
        \chmod($path, 0o755);
    }

    /**
     * 创建总是失败的可执行文件
     */
    public static function createFailingExecutable(string $path, string $errorMessage = '解析失败'): void
    {
        $scriptContent = "#!/bin/bash\necho '{$errorMessage}' >&2\nexit 1";
        \file_put_contents($path, $scriptContent);
        \chmod($path, 0o755);
    }

    /**
     * 创建测试用的 API 文件
     */
    public static function createTestApiFile(string $path, string $content = null): void
    {
        $content ??= self::getDefaultApiContent();
        \file_put_contents($path, $content);
    }

    /**
     * 获取默认的模拟输出（兼容低版本 Go）
     */
    public static function getDefaultMockOutput(): string
    {
        return \json_encode([
            'info' => [
                'title' => '测试API',
                'desc' => '测试描述',
                'author' => '测试作者',
                'email' => 'test@example.com',
                'version' => 'v1.0.0',
            ],
            'types' => [
                [
                    'name' => 'User',
                    'fields' => [
                        ['name' => 'Id', 'type' => 'int64'],
                        ['name' => 'Name', 'type' => 'string'],
                    ],
                ],
            ],
            'services' => [
                [
                    'name' => 'UserService',
                    'methods' => [
                        [
                            'name' => 'GetUser',
                            'path' => '/api/user/:id',
                            'method' => 'GET',
                        ],
                    ],
                ],
            ],
        ], \JSON_UNESCAPED_UNICODE);
    }

    /**
     * 获取默认的 API 文件内容
     */
    public static function getDefaultApiContent(): string
    {
        return <<<'EOD'
info(
    title: "测试API"
    desc: "测试描述"
    author: "测试作者"
    email: "test@example.com"
    version: "v1.0.0"
)

type User {
    Id   int64  `json:"id"`
    Name string `json:"name"`
}

type GetUserReq {
    Id int64 `path:"id"`
}

type GetUserResp {
    User User `json:"user"`
}

service UserService {
    @handler GetUser
    get /api/user/:id (GetUserReq) returns (GetUserResp)
}
EOD;
    }

    /**
     * 创建兼容 Go 1.21 的主程序文件
     *
     * @param string $path 文件路径
     * @param bool $useGenerics 是否使用泛型
     * @return void
     */
    public static function createGoMainFile(string $path, bool $useGenerics = false): void
    {
        if ($useGenerics && self::isGoVersionAtLeast('1.21')) {
            // 使用 Go 1.21+ 的泛型特性
            $goContent = self::getGoMainContentWithGenerics();
        } else {
            // 使用传统语法，兼容旧版本
            $goContent = self::getGoMainContentTraditional();
        }

        \file_put_contents($path, $goContent);
    }

    /**
     * 获取使用传统语法的 Go 主程序内容
     */
    private static function getGoMainContentTraditional(): string
    {
        return <<<'EOD'
package main

import (
    "encoding/json"
    "fmt"
    "os"
)

type Info struct {
    Title   string `json:"title"`
    Desc    string `json:"desc"`
    Author  string `json:"author"`
    Email   string `json:"email"`
    Version string `json:"version"`
}

type Field struct {
    Name string `json:"name"`
    Type string `json:"type"`
}

type Type struct {
    Name   string  `json:"name"`
    Fields []Field `json:"fields"`
}

type Method struct {
    Name   string `json:"name"`
    Path   string `json:"path"`
    Method string `json:"method"`
}

type Service struct {
    Name    string   `json:"name"`
    Methods []Method `json:"methods"`
}

type ApiSpec struct {
    Info     Info      `json:"info"`
    Types    []Type    `json:"types"`
    Services []Service `json:"services"`
}

func main() {
    if len(os.Args) < 2 {
        fmt.Fprintf(os.Stderr, "需要 API 文件参数\n")
        os.Exit(1)
    }

    // 模拟解析结果
    spec := ApiSpec{
        Info: Info{
            Title:   "测试API",
            Desc:    "测试描述",
            Author:  "测试作者",
            Email:   "test@example.com",
            Version: "v1.0.0",
        },
        Types: []Type{
            {
                Name: "User",
                Fields: []Field{
                    {Name: "Id", Type: "int64"},
                    {Name: "Name", Type: "string"},
                },
            },
        },
        Services: []Service{
            {
                Name: "UserService",
                Methods: []Method{
                    {
                        Name:   "GetUser",
                        Path:   "/api/user/:id",
                        Method: "GET",
                    },
                },
            },
        },
    }

    jsonData, err := json.Marshal(spec)
    if err != nil {
        fmt.Fprintf(os.Stderr, "JSON 序列化错误: %v\n", err)
        os.Exit(1)
    }

    fmt.Println(string(jsonData))
}
EOD;
    }

    /**
     * 获取使用泛型的 Go 主程序内容（Go 1.21+）
     */
    private static function getGoMainContentWithGenerics(): string
    {
        return <<<'EOD'
package main

import (
    "encoding/json"
    "fmt"
    "os"
)

type Result[T any] struct {
    Data T `json:"data"`
    Code int `json:"code"`
}

type Info struct {
    Title   string `json:"title"`
    Desc    string `json:"desc"`
    Author  string `json:"author"`
    Email   string `json:"email"`
    Version string `json:"version"`
}

type Field struct {
    Name string `json:"name"`
    Type string `json:"type"`
}

type Type struct {
    Name   string  `json:"name"`
    Fields []Field `json:"fields"`
}

type Method struct {
    Name   string `json:"name"`
    Path   string `json:"path"`
    Method string `json:"method"`
}

type Service struct {
    Name    string   `json:"name"`
    Methods []Method `json:"methods"`
}

type ApiSpec struct {
    Info     Info      `json:"info"`
    Types    []Type    `json:"types"`
    Services []Service `json:"services"`
}

func main() {
    if len(os.Args) < 2 {
        fmt.Fprintf(os.Stderr, "需要 API 文件参数\n")
        os.Exit(1)
    }

    // 使用泛型包装结果
    spec := ApiSpec{
        Info: Info{
            Title:   "测试API",
            Desc:    "测试描述",
            Author:  "测试作者",
            Email:   "test@example.com",
            Version: "v1.0.0",
        },
        Types: []Type{
            {
                Name: "User",
                Fields: []Field{
                    {Name: "Id", Type: "int64"},
                    {Name: "Name", Type: "string"},
                },
            },
        },
        Services: []Service{
            {
                Name: "UserService",
                Methods: []Method{
                    {
                        Name:   "GetUser",
                        Path:   "/api/user/:id",
                        Method: "GET",
                    },
                },
            },
        },
    }

    jsonData, err := json.Marshal(spec)
    if err != nil {
        fmt.Fprintf(os.Stderr, "JSON 序列化错误: %v\n", err)
        os.Exit(1)
    }

    fmt.Println(string(jsonData))
}
EOD;
    }

    /**
     * 创建 go.mod 文件
     */
    public static function createGoMod(string $dir, string $moduleName = 'test-parser', string $goVersion = '1.21'): void
    {
        $content = "module {$moduleName}\n\ngo {$goVersion}\n";
        \file_put_contents($dir . '/go.mod', $content);
    }

    /**
     * 检查是否具有 Go 环境
     */
    public static function hasGoEnvironment(): bool
    {
        $output = [];
        $returnCode = 0;
        \exec('go version 2>/dev/null', $output, $returnCode);
        return 0 === $returnCode && ! empty($output);
    }

    /**
     * 获取 Go 版本信息
     */
    public static function getGoVersion(): ?string
    {
        if (! self::hasGoEnvironment()) {
            return null;
        }

        $output = [];
        $returnCode = 0;
        \exec('go version 2>/dev/null', $output, $returnCode);

        if (0 === $returnCode && ! empty($output)) {
            $versionString = \implode(' ', $output);
            if (\preg_match('/go(\d+\.\d+(?:\.\d+)?)/', $versionString, $matches)) {
                return $matches[1];
            }
        }

        return null;
    }

    /**
     * 检查 Go 版本是否满足最低要求
     */
    public static function isGoVersionAtLeast(string $minVersion): bool
    {
        $currentVersion = self::getGoVersion();
        if (null === $currentVersion) {
            return false;
        }

        return \version_compare($currentVersion, $minVersion, '>=');
    }

    /**
     * 检查当前是否为 macOS ARM 环境
     */
    public static function isMacOSARM(): bool
    {
        return 'Darwin' === \php_uname('s') && \in_array(\php_uname('m'), ['arm64', 'aarch64']);
    }

    /**
     * 获取系统架构信息
     */
    public static function getSystemArchitectures(): array
    {
        return [
            'macOS ARM64' => ['Darwin', 'arm64'],
            'macOS x86_64' => ['Darwin', 'x86_64'],
            'Linux x86_64' => ['Linux', 'x86_64'],
            'Linux ARM64' => ['Linux', 'aarch64'],
            'Windows x86_64' => ['WINNT', 'AMD64'],
        ];
    }

    /**
     * 创建无效的 API 文件用于错误测试
     */
    public static function createInvalidApiFile(string $path): void
    {
        $invalidContent = <<<'EOD'
invalid syntax here
{{{
missing brackets
EOD;
        \file_put_contents($path, $invalidContent);
    }

    /**
     * 获取测试用的错误消息
     */
    public static function getTestErrorMessages(): array
    {
        return [
            'file_not_found' => 'API 文件不存在',
            'file_not_executable' => 'API 解析器文件不可执行',
            'execution_failed' => 'API 解析执行失败',
            'invalid_json' => '无效的 JSON 输出',
            'go_version_too_low' => 'Go 版本过低，需要 1.21 或更高版本',
        ];
    }

    /**
     * 创建包含多个 API 文件的测试场景
     */
    public static function createMultipleApiFiles(string $baseDir): array
    {
        $files = [];

        // API 文件 1
        $file1 = $baseDir . '/user.api';
        self::createTestApiFile($file1);
        $files[] = $file1;

        // API 文件 2
        $file2 = $baseDir . '/product.api';
        $productContent = <<<'EOD'
info(
    title: "产品API"
    desc: "产品管理接口"
    version: "v1.0.0"
)

type Product {
    Id    int64  `json:"id"`
    Name  string `json:"name"`
    Price int64  `json:"price"`
}

service ProductService {
    @handler GetProduct
    get /api/product/:id (GetProductReq) returns (GetProductResp)
}
EOD;
        self::createTestApiFile($file2, $productContent);
        $files[] = $file2;

        // 无效的 API 文件
        $file3 = $baseDir . '/invalid.api';
        self::createInvalidApiFile($file3);
        $files[] = $file3;

        return $files;
    }

    /**
     * 验证 Go 编译环境
     */
    public static function validateGoEnvironment(): array
    {
        $info = [
            'has_go' => false,
            'version' => null,
            'is_compatible' => false,
            'error' => null,
        ];

        if (! self::hasGoEnvironment()) {
            $info['error'] = 'Go 环境未安装';
            return $info;
        }

        $info['has_go'] = true;
        $version = self::getGoVersion();
        $info['version'] = $version;

        if (null === $version) {
            $info['error'] = '无法获取 Go 版本信息';
            return $info;
        }

        $info['is_compatible'] = self::isGoVersionAtLeast(self::MIN_GO_VERSION);

        if (! $info['is_compatible']) {
            $info['error'] = "Go 版本过低，当前: {$version}，需要: " . self::MIN_GO_VERSION . ' 或更高';
        }

        return $info;
    }

    /**
     * 创建简化的测试可执行文件（用于 CI 环境）
     */
    public static function createMinimalExecutable(string $path): void
    {
        $minimalOutput = \json_encode([
            'info' => ['title' => 'CI测试'],
            'types' => [],
            'services' => [],
        ]);

        $scriptContent = "#!/bin/bash\necho '{$minimalOutput}'";
        \file_put_contents($path, $scriptContent);
        \chmod($path, 0o755);
    }
}
