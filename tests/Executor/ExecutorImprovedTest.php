<?php

declare(strict_types=1);

namespace GoZeroApiParser\Tests\Executor;

use GoZeroApiParser\Executor;
use PHPUnit\Framework\TestCase;

/**
 * 改进的 Executor 测试类
 * 专注于核心功能，清晰的测试职责分离
 */
class ExecutorImprovedTest extends TestCase
{
    private string $tempDir;
    private string $validExecutable;
    private string $invalidExecutable;
    private string $testApiFile;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tempDir = TestHelper::createTempDir('executor_improved_');

        // 创建有效的可执行文件（核心测试用）
        $this->validExecutable = $this->tempDir . '/valid-parser';
        TestHelper::createMockExecutable($this->validExecutable);

        // 创建无效的可执行文件
        $this->invalidExecutable = $this->tempDir . '/invalid-parser';
        \file_put_contents($this->invalidExecutable, 'not executable');

        // 创建测试 API 文件
        $this->testApiFile = $this->tempDir . '/test.api';
        TestHelper::createTestApiFile($this->testApiFile);
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        TestHelper::recursiveDelete($this->tempDir);
    }

    // ========== 核心执行功能测试 ==========

    /**
     * 测试构造函数 - 使用有效的可执行文件路径
     */
    public function testConstructorWithValidExecutable(): void
    {
        $executor = new Executor($this->validExecutable);

        $this->assertEquals($this->validExecutable, $executor->getExecutablePath());
        $this->assertFalse($executor->isAutoDetected());
    }

    /**
     * 测试构造函数 - 使用不存在的可执行文件
     */
    public function testConstructorWithNonexistentExecutable(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('API 解析器可执行文件不存在');

        new Executor('/path/to/nonexistent/file');
    }

    /**
     * 测试构造函数 - 使用不可执行的文件
     */
    public function testConstructorWithNonExecutableFile(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('API 解析器文件不可执行');

        new Executor($this->invalidExecutable);
    }

    /**
     * 测试执行成功的情况
     */
    public function testExecuteSuccess(): void
    {
        $executor = new Executor($this->validExecutable);
        $result = $executor->execute($this->testApiFile);

        $this->assertIsString($result);
        $this->assertJson($result);

        // 验证返回的 JSON 结构
        $parsed = \json_decode($result, true);
        $this->assertArrayHasKey('info', $parsed);
        $this->assertArrayHasKey('types', $parsed);
        $this->assertEquals('测试API', $parsed['info']['title']);
    }

    /**
     * 测试执行不存在的 API 文件
     */
    public function testExecuteWithNonexistentApiFile(): void
    {
        $executor = new Executor($this->validExecutable);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('API 文件不存在');

        $executor->execute('/path/to/nonexistent.api');
    }

    /**
     * 测试执行失败的情况
     */
    public function testExecuteFailure(): void
    {
        $failingExecutable = $this->tempDir . '/failing-parser';
        TestHelper::createFailingExecutable($failingExecutable, '解析错误');

        $executor = new Executor($failingExecutable);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('API 解析执行失败');

        $executor->execute($this->testApiFile);
    }

    /**
     * 测试批量执行 - 全部成功
     */
    public function testExecuteMultipleAllSuccess(): void
    {
        $executor = new Executor($this->validExecutable);

        $files = TestHelper::createMultipleApiFiles($this->tempDir);
        $validFiles = \array_slice($files, 0, 2); // 只取前两个有效文件

        $results = $executor->executeMultiple($validFiles);

        $this->assertCount(2, $results);
        foreach ($validFiles as $file) {
            $this->assertTrue($results[$file]['success']);
            $this->assertArrayHasKey('output', $results[$file]);
            $this->assertJson($results[$file]['output']);
        }
    }

    /**
     * 测试批量执行 - 包含失败情况
     */
    public function testExecuteMultipleWithFailures(): void
    {
        $executor = new Executor($this->validExecutable);

        $testFiles = [
            $this->testApiFile,                    // 有效文件
            '/path/to/nonexistent.api',             // 无效文件
        ];

        $results = $executor->executeMultiple($testFiles);

        $this->assertCount(2, $results);
        $this->assertTrue($results[$this->testApiFile]['success']);
        $this->assertFalse($results['/path/to/nonexistent.api']['success']);
        $this->assertArrayHasKey('output', $results[$this->testApiFile]);
        $this->assertArrayHasKey('error', $results['/path/to/nonexistent.api']);
    }

    /**
     * 测试设置可执行文件路径
     */
    public function testSetExecutablePath(): void
    {
        $executor = new Executor($this->validExecutable);

        $newExecutable = $this->tempDir . '/new-parser';
        TestHelper::createMockExecutable($newExecutable);

        $executor->setExecutablePath($newExecutable);

        $this->assertEquals($newExecutable, $executor->getExecutablePath());
        $this->assertFalse($executor->isAutoDetected());
    }

    /**
     * 测试设置无效的可执行文件路径
     */
    public function testSetInvalidExecutablePath(): void
    {
        $executor = new Executor($this->validExecutable);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('API 解析器可执行文件不存在');

        $executor->setExecutablePath('/path/to/nonexistent');
    }

    // ========== 系统信息和环境检测测试 ==========

    /**
     * 测试获取系统信息
     */
    public function testGetSystemInfo(): void
    {
        $executor = new Executor($this->validExecutable);
        $systemInfo = $executor->getSystemInfo();

        $this->assertIsArray($systemInfo);
        $this->assertArrayHasKey('os', $systemInfo);
        $this->assertArrayHasKey('arch', $systemInfo);
        $this->assertArrayHasKey('php_version', $systemInfo);
        $this->assertArrayHasKey('go_version', $systemInfo);
        $this->assertArrayHasKey('go_available', $systemInfo);

        $this->assertEquals(\php_uname('s'), $systemInfo['os']);
        $this->assertEquals(\php_uname('m'), $systemInfo['arch']);
        $this->assertEquals(\PHP_VERSION, $systemInfo['php_version']);
        $this->assertIsBool($systemInfo['go_available']);
    }

    /**
     * 测试获取路径信息
     */
    public function testGetPathInfo(): void
    {
        $executor = new Executor($this->validExecutable);
        $pathInfo = $executor->getPathInfo();

        $this->assertIsArray($pathInfo);
        $this->assertArrayHasKey('current_file', $pathInfo);
        $this->assertArrayHasKey('package_directory', $pathInfo);
        $this->assertArrayHasKey('working_directory', $pathInfo);
        $this->assertArrayHasKey('possible_executables', $pathInfo);
        $this->assertArrayHasKey('file_existence', $pathInfo);

        $this->assertIsArray($pathInfo['possible_executables']);
        $this->assertIsArray($pathInfo['file_existence']);
    }

    // ========== 边界条件和错误处理测试 ==========

    /**
     * 测试空的 API 文件
     */
    public function testExecuteWithEmptyApiFile(): void
    {
        $emptyFile = $this->tempDir . '/empty.api';
        \file_put_contents($emptyFile, '');

        $executor = new Executor($this->validExecutable);

        // 这里应该成功执行，即使文件为空
        // 具体行为取决于可执行文件的实现
        $result = $executor->execute($emptyFile);
        $this->assertIsString($result);
    }

    /**
     * 测试包含特殊字符的文件路径
     */
    public function testExecuteWithSpecialCharactersInPath(): void
    {
        $specialDir = $this->tempDir . '/test dir with spaces';
        \mkdir($specialDir, 0o755, true);

        $specialFile = $specialDir . '/test file.api';
        TestHelper::createTestApiFile($specialFile);

        $executor = new Executor($this->validExecutable);
        $result = $executor->execute($specialFile);

        $this->assertIsString($result);
        $this->assertJson($result);
    }

    /**
     * 测试权限问题 - 不可读的 API 文件
     */
    public function testExecuteWithUnreadableApiFile(): void
    {
        if (\PHP_OS_FAMILY === 'Windows') {
            $this->markTestSkipped('权限测试在 Windows 上行为不同');
        }

        $unreadableFile = $this->tempDir . '/unreadable.api';
        TestHelper::createTestApiFile($unreadableFile);
        \chmod($unreadableFile, 0o000); // 移除所有权限

        $executor = new Executor($this->validExecutable);

        try {
            // 这应该失败，因为文件不可读
            $executor->execute($unreadableFile);
            $this->fail('应该抛出异常');
        } catch (\Exception $e) {
            // 恢复权限以便清理
            \chmod($unreadableFile, 0o644);
            $this->assertInstanceOf(\RuntimeException::class, $e);
        }
    }

    // ========== 性能和稳定性测试 ==========

    /**
     * 测试大量 API 文件的批量处理
     */
    public function testExecuteMultipleWithManyFiles(): void
    {
        $executor = new Executor($this->validExecutable);

        // 创建多个测试文件
        $files = [];
        for ($i = 1; $i <= 10; $i++) {
            $file = $this->tempDir . "/test_{$i}.api";
            TestHelper::createTestApiFile($file);
            $files[] = $file;
        }

        $results = $executor->executeMultiple($files);

        $this->assertCount(10, $results);
        foreach ($files as $file) {
            $this->assertTrue($results[$file]['success']);
            $this->assertArrayHasKey('output', $results[$file]);
        }
    }

    /**
     * 测试重复执行的稳定性
     */
    public function testRepeatedExecution(): void
    {
        $executor = new Executor($this->validExecutable);

        // 重复执行同一个文件多次
        for ($i = 0; $i < 5; $i++) {
            $result = $executor->execute($this->testApiFile);
            $this->assertIsString($result);
            $this->assertJson($result);

            $parsed = \json_decode($result, true);
            $this->assertEquals('测试API', $parsed['info']['title']);
        }
    }
}
