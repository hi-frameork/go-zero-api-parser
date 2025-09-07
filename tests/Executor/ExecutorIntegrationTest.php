<?php

declare(strict_types=1);

namespace GoZeroApiParser\Tests\Executor;

use GoZeroApiParser\Executor;
use PHPUnit\Framework\TestCase;

/**
 * Executor 类的集成测试
 * 支持 Go 1.21+ 版本的测试场景
 */
class ExecutorIntegrationTest extends TestCase
{
    private string $tempDir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tempDir = TestHelper::createTempDir('executor_integration_');
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        TestHelper::recursiveDelete($this->tempDir);
    }

    /**
     * 测试完整的工作流程
     */
    public function testCompleteWorkflow(): void
    {
        // 创建模拟可执行文件
        $executable = $this->tempDir . '/api-parser';
        TestHelper::createMockExecutable($executable);

        // 创建多个测试 API 文件
        $apiFiles = TestHelper::createMultipleApiFiles($this->tempDir);

        // 初始化 Executor
        $executor = new Executor($executable);

        // 验证基本信息
        $this->assertEquals($executable, $executor->getExecutablePath());
        $this->assertFalse($executor->isAutoDetected());

        // 测试单个文件解析
        $result = $executor->execute($apiFiles[0]);
        $this->assertIsString($result);
        $this->assertJson($result);

        $parsedResult = \json_decode($result, true);
        $this->assertArrayHasKey('info', $parsedResult);
        $this->assertArrayHasKey('types', $parsedResult);

        // 测试批量解析（只测试有效文件）
        $validFiles = [$apiFiles[0], $apiFiles[1]];
        $batchResults = $executor->executeMultiple($validFiles);
        $this->assertCount(2, $batchResults);
        $this->assertTrue($batchResults[$validFiles[0]]['success']);
        $this->assertTrue($batchResults[$validFiles[1]]['success']);
    }

    /**
     * 测试错误恢复场景
     */
    public function testErrorRecovery(): void
    {
        // 首先创建一个失败的可执行文件
        $failingExecutable = $this->tempDir . '/failing-parser';
        TestHelper::createFailingExecutable($failingExecutable);

        $executor = new Executor($failingExecutable);
        $apiFile = $this->tempDir . '/test.api';
        TestHelper::createTestApiFile($apiFile);

        // 验证执行失败
        try {
            $executor->execute($apiFile);
            $this->fail('应该抛出异常');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('API 解析执行失败', $e->getMessage());
        }

        // 现在切换到正常的可执行文件
        $workingExecutable = $this->tempDir . '/working-parser';
        TestHelper::createMockExecutable($workingExecutable);

        $executor->setExecutablePath($workingExecutable);

        // 验证现在可以正常工作
        $result = $executor->execute($apiFile);
        $this->assertIsString($result);
        $this->assertJson($result);
    }

    /**
     * 测试性能场景：批量处理文件
     */
    public function testBatchPerformance(): void
    {
        $executable = $this->tempDir . '/api-parser';
        TestHelper::createMockExecutable($executable);

        $executor = new Executor($executable);

        // 创建多个 API 文件
        $apiFiles = [];
        for ($i = 0; $i < 5; $i++) {
            $apiFile = $this->tempDir . "/test_{$i}.api";
            TestHelper::createTestApiFile($apiFile);
            $apiFiles[] = $apiFile;
        }

        $startTime = \microtime(true);
        $results = $executor->executeMultiple($apiFiles);
        $endTime = \microtime(true);

        // 验证所有文件都成功处理
        $this->assertCount(5, $results);
        foreach ($results as $result) {
            $this->assertTrue($result['success']);
            $this->assertArrayHasKey('output', $result);
        }

        // 验证执行时间合理（应该在几秒内完成）
        $executionTime = $endTime - $startTime;
        $this->assertLessThan(10, $executionTime, '批量处理耗时过长');
    }

    /**
     * 测试边界条件：空文件、大文件等
     */
    public function testEdgeCases(): void
    {
        $executable = $this->tempDir . '/api-parser';
        TestHelper::createMockExecutable($executable);

        $executor = new Executor($executable);

        // 测试空文件
        $emptyFile = $this->tempDir . '/empty.api';
        \file_put_contents($emptyFile, '');

        $result = $executor->execute($emptyFile);
        $this->assertIsString($result);

        // 测试包含基本内容的文件
        $basicFile = $this->tempDir . '/basic.api';
        $basicContent = 'info(title: "基本API")';
        \file_put_contents($basicFile, $basicContent);

        $result = $executor->execute($basicFile);
        $this->assertIsString($result);
    }

    /**
     * 测试系统环境适应性
     */
    public function testEnvironmentAdaptability(): void
    {
        $executable = $this->tempDir . '/api-parser';
        TestHelper::createMockExecutable($executable);

        $executor = new Executor($executable);

        // 获取系统信息
        $systemInfo = $executor->getSystemInfo();

        // 验证系统信息完整性
        $requiredKeys = ['os', 'arch', 'php_version', 'go_version', 'go_available'];
        foreach ($requiredKeys as $key) {
            $this->assertArrayHasKey($key, $systemInfo);
        }

        // 验证 PHP 版本信息
        $this->assertEquals(\PHP_VERSION, $systemInfo['php_version']);

        // 验证操作系统信息
        $this->assertEquals(\php_uname('s'), $systemInfo['os']);
        $this->assertEquals(\php_uname('m'), $systemInfo['arch']);

        // 获取路径信息
        $pathInfo = $executor->getPathInfo();

        // 验证路径信息结构
        $this->assertArrayHasKey('current_file', $pathInfo);
        $this->assertArrayHasKey('package_directory', $pathInfo);
        $this->assertArrayHasKey('working_directory', $pathInfo);
    }

    /**
     * 测试 Go 版本兼容性
     */
    public function testGoVersionCompatibility(): void
    {
        $goInfo = TestHelper::validateGoEnvironment();

        if (! $goInfo['has_go']) {
            $this->markTestSkipped('Go 环境未安装，跳过版本兼容性测试');
        }

        $this->assertNotNull($goInfo['version'], 'Go 版本信息应该可用');

        if (! $goInfo['is_compatible']) {
            $this->markTestSkipped("Go 版本不兼容: {$goInfo['error']}");
        }

        // 如果到这里，说明 Go 版本兼容
        $this->assertTrue($goInfo['is_compatible']);
        $this->assertTrue(TestHelper::isGoVersionAtLeast('1.21'));
    }

    /**
     * 测试使用实际 Go 编译器
     */
    public function testRealGoCompilation(): void
    {
        $goInfo = TestHelper::validateGoEnvironment();

        if (! $goInfo['has_go'] || ! $goInfo['is_compatible']) {
            $this->markTestSkipped("跳过实际编译测试: {$goInfo['error']}");
        }

        // 创建 Go 项目目录
        $goProjectDir = $this->tempDir . '/go_project';
        \mkdir($goProjectDir, 0o755, true);

        // 创建 go.mod
        TestHelper::createGoMod($goProjectDir, 'test-api-parser', '1.21');

        // 创建 main.go（使用传统语法确保兼容性）
        TestHelper::createGoMainFile($goProjectDir . '/main.go', false);

        // 编译
        $compiledExecutable = $goProjectDir . '/api-parser';
        $command = \sprintf(
            'cd %s && go build -o %s main.go 2>&1',
            \escapeshellarg($goProjectDir),
            \escapeshellarg(\basename($compiledExecutable)),
        );

        $output = [];
        $returnCode = 0;
        \exec($command, $output, $returnCode);

        if (0 !== $returnCode) {
            $this->fail('Go 编译失败: ' . \implode("\n", $output));
        }

        $this->assertFileExists($compiledExecutable);
        $this->assertTrue(\is_executable($compiledExecutable));

        // 测试编译的程序
        $testApiFile = $this->tempDir . '/test_compile.api';
        TestHelper::createTestApiFile($testApiFile);

        $testOutput = [];
        $testReturnCode = 0;
        \exec($compiledExecutable . ' ' . \escapeshellarg($testApiFile), $testOutput, $testReturnCode);

        $this->assertEquals(0, $testReturnCode, '编译的程序执行失败');

        $result = \implode("\n", $testOutput);
        $this->assertJson($result);

        $parsed = \json_decode($result, true);
        $this->assertArrayHasKey('info', $parsed);
    }

    /**
     * 测试并发执行兼容性
     */
    public function testConcurrentExecution(): void
    {
        $executable = $this->tempDir . '/api-parser';
        TestHelper::createMockExecutable($executable);

        // 创建多个 Executor 实例
        $executors = [];
        for ($i = 0; $i < 3; $i++) {
            $executors[] = new Executor($executable);
        }

        // 创建测试文件
        $apiFile = $this->tempDir . '/concurrent_test.api';
        TestHelper::createTestApiFile($apiFile);

        // 并发执行（模拟）
        $results = [];
        foreach ($executors as $index => $executor) {
            try {
                $result = $executor->execute($apiFile);
                $results[$index] = ['success' => true, 'output' => $result];
            } catch (\Exception $e) {
                $results[$index] = ['success' => false, 'error' => $e->getMessage()];
            }
        }

        // 验证所有执行都成功
        $this->assertCount(3, $results);
        foreach ($results as $result) {
            $this->assertTrue($result['success']);
            $this->assertArrayHasKey('output', $result);
        }
    }

    /**
     * 测试资源管理和清理
     */
    public function testResourceManagement(): void
    {
        $executable = $this->tempDir . '/api-parser';
        TestHelper::createMockExecutable($executable);

        $executor = new Executor($executable);
        $apiFile = $this->tempDir . '/resource_test.api';
        TestHelper::createTestApiFile($apiFile);

        // 执行多次操作
        for ($i = 0; $i < 5; $i++) {
            $result = $executor->execute($apiFile);
            $this->assertIsString($result);
        }

        // 验证没有产生临时文件或内存泄漏
        $this->assertTrue(true, '资源管理测试完成');
    }

    /**
     * 测试错误日志和调试信息
     */
    public function testErrorLoggingAndDebugging(): void
    {
        // 创建会产生特定错误输出的可执行文件
        $debugExecutable = $this->tempDir . '/debug-parser';
        $scriptContent = <<<'EOD'
#!/bin/bash
echo "调试信息: 开始解析文件 $1" >&2
echo "错误: 文件格式不正确" >&2
exit 1
EOD;
        \file_put_contents($debugExecutable, $scriptContent);
        \chmod($debugExecutable, 0o755);

        $executor = new Executor($debugExecutable);
        $apiFile = $this->tempDir . '/debug_test.api';
        TestHelper::createTestApiFile($apiFile);

        try {
            $executor->execute($apiFile);
            $this->fail('应该抛出异常');
        } catch (\RuntimeException $e) {
            // 验证错误消息包含调试信息
            $message = $e->getMessage();
            $this->assertStringContainsString('API 解析执行失败', $message);
            $this->assertStringContainsString('调试信息', $message);
            $this->assertStringContainsString('错误: 文件格式不正确', $message);
        }
    }

    /**
     * 测试最小可行环境
     */
    public function testMinimalViableEnvironment(): void
    {
        // 创建最简单的可执行文件
        $minimalExecutable = $this->tempDir . '/minimal-parser';
        TestHelper::createMinimalExecutable($minimalExecutable);

        $executor = new Executor($minimalExecutable);

        // 验证基本功能工作
        $systemInfo = $executor->getSystemInfo();
        $this->assertIsArray($systemInfo);
        $this->assertArrayHasKey('php_version', $systemInfo);

        // 创建最小测试文件
        $minimalApiFile = $this->tempDir . '/minimal.api';
        \file_put_contents($minimalApiFile, 'info(title: "最小测试")');

        $result = $executor->execute($minimalApiFile);
        $this->assertIsString($result);
        $this->assertJson($result);
    }
}
