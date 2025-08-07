<?php

declare(strict_types=1);

namespace GoZeroApiParser\Tests\Executor;

use GoZeroApiParser\Executor;
use PHPUnit\Framework\TestCase;

/**
 * Executor 环境检测功能专项测试
 * 专注于测试自动检测、Go 环境检查等功能
 */
class ExecutorEnvironmentTest extends TestCase
{
    private string $tempDir;
    private \ReflectionClass $executorReflection;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tempDir = TestHelper::createTempDir('executor_env_');

        // 创建反射对象用于测试 protected 方法
        $this->executorReflection = new \ReflectionClass(Executor::class);
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        TestHelper::recursiveDelete($this->tempDir);
    }

    // ========== Go 环境检测测试 ==========

    /**
     * 测试 Go 环境检测
     */
    public function testHasGoEnvironment(): void
    {
        $validExecutable = $this->tempDir . '/test-parser';
        TestHelper::createMockExecutable($validExecutable);

        $executor = new Executor($validExecutable);

        $method = $this->executorReflection->getMethod('hasGoEnvironment');
        $method->setAccessible(true);

        $hasGo = $method->invoke($executor);
        $this->assertIsBool($hasGo);

        // 与 TestHelper 的检测结果应该一致
        $this->assertEquals(TestHelper::hasGoEnvironment(), $hasGo);
    }

    /**
     * 测试 macOS ARM 检测逻辑
     */
    public function testMacOSARMDetection(): void
    {
        $validExecutable = $this->tempDir . '/test-parser';
        TestHelper::createMockExecutable($validExecutable);

        $executor = new Executor($validExecutable);

        $method = $this->executorReflection->getMethod('isMacOSARM');
        $method->setAccessible(true);

        $isMacOSARM = $method->invoke($executor);
        $this->assertIsBool($isMacOSARM);

        // 与 TestHelper 的检测结果应该一致
        $this->assertEquals(TestHelper::isMacOSARM(), $isMacOSARM);

        // 如果是 macOS，验证架构检测
        if ('Darwin' === \php_uname('s')) {
            $arch = \php_uname('m');
            $expectedARM = \in_array($arch, ['arm64', 'aarch64']);
            $this->assertEquals($expectedARM, $isMacOSARM);
        }
    }

    /**
     * 测试 Go 安装提示信息生成
     */
    public function testGetGoInstallationMessage(): void
    {
        $validExecutable = $this->tempDir . '/test-parser';
        TestHelper::createMockExecutable($validExecutable);

        $executor = new Executor($validExecutable);

        $method = $this->executorReflection->getMethod('getGoInstallationMessage');
        $method->setAccessible(true);

        $message = $method->invoke($executor);

        $this->assertIsString($message);
        $this->assertStringContainsString('未找到可用的 API 解析器', $message);
        $this->assertStringContainsString('Go 语言环境', $message);
        $this->assertStringContainsString(\php_uname('s'), $message);
        $this->assertStringContainsString(\php_uname('m'), $message);

        // 验证不同操作系统的安装指南
        $os = \php_uname('s');
        switch ($os) {
            case 'Darwin':
                $this->assertStringContainsString('macOS 安装方式', $message);
                $this->assertStringContainsString('brew install go', $message);
                break;

            case 'Linux':
                $this->assertStringContainsString('Linux 安装方式', $message);
                $this->assertStringContainsString('apt-get install', $message);
                break;

            case 'WINNT':
                $this->assertStringContainsString('Windows 安装方式', $message);
                $this->assertStringContainsString('Chocolatey', $message);
                break;
        }
    }

    // ========== 自动检测功能测试 ==========

    /**
     * 测试自动检测失败的情况
     */
    public function testAutoDetectFailureWithoutGoEnvironment(): void
    {
        if (TestHelper::hasGoEnvironment()) {
            $this->markTestSkipped('此测试需要在没有 Go 环境的情况下运行');
        }

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('未找到可用的 API 解析器');

        new Executor; // 触发自动检测
    }

    // ========== 包目录检测测试 ==========

    /**
     * 测试包目录检测逻辑
     */
    public function testGetPackageDirectory(): void
    {
        $validExecutable = $this->tempDir . '/test-parser';
        TestHelper::createMockExecutable($validExecutable);

        $executor = new Executor($validExecutable);

        $method = $this->executorReflection->getMethod('getPackageDirectory');
        $method->setAccessible(true);

        $packageDir = $method->invoke($executor);

        $this->assertIsString($packageDir);
        $this->assertDirectoryExists($packageDir);

        // 验证是否是合理的包目录路径
        $expectedPackageDir = \dirname(__DIR__, 2); // 应该是项目根目录
        $this->assertEquals($expectedPackageDir, $packageDir);
    }

    // ========== 系统架构相关测试 ==========

    /**
     * 测试不同系统架构的检测
     */
    public function testSystemArchitectureDetection(): void
    {
        $systemInfo = [
            'os' => \php_uname('s'),
            'arch' => \php_uname('m'),
        ];

        $validExecutable = $this->tempDir . '/test-parser';
        TestHelper::createMockExecutable($validExecutable);

        $executor = new Executor($validExecutable);
        $executorSystemInfo = $executor->getSystemInfo();

        $this->assertEquals($systemInfo['os'], $executorSystemInfo['os']);
        $this->assertEquals($systemInfo['arch'], $executorSystemInfo['arch']);

        // 验证已知的架构组合
        $knownArchitectures = TestHelper::getSystemArchitectures();
        $currentSystem = [$systemInfo['os'], $systemInfo['arch']];

        $isKnownArchitecture = false;
        foreach ($knownArchitectures as $name => $arch) {
            if ($arch[0] === $currentSystem[0] && $arch[1] === $currentSystem[1]) {
                $isKnownArchitecture = true;
                break;
            }
        }

        // 如果不是已知架构，至少确保检测到的值不为空
        if (! $isKnownArchitecture) {
            $this->assertNotEmpty($systemInfo['os']);
            $this->assertNotEmpty($systemInfo['arch']);
        }
    }

    // ========== Go 版本兼容性测试 ==========

    /**
     * 测试 Go 版本兼容性（仅在有 Go 环境时运行）
     *
     * @group requires-go
     */
    public function testGoVersionCompatibility(): void
    {
        if (! TestHelper::hasGoEnvironment()) {
            $this->markTestSkipped('需要 Go 环境来运行此测试');
        }

        $goEnvironmentInfo = TestHelper::validateGoEnvironment();

        $this->assertTrue($goEnvironmentInfo['has_go']);
        $this->assertNotNull($goEnvironmentInfo['version']);

        if (! $goEnvironmentInfo['is_compatible']) {
            $this->markTestSkipped(
                "Go 版本不兼容: {$goEnvironmentInfo['error']}",
            );
        }

        $this->assertTrue($goEnvironmentInfo['is_compatible']);
    }

    // ========== 边界条件测试 ==========

    /**
     * 测试路径中包含特殊字符的情况
     */
    public function testPathWithSpecialCharacters(): void
    {
        $specialDir = $this->tempDir . '/test-dir with spaces & symbols!';
        \mkdir($specialDir, 0o755, true);

        $validExecutable = $specialDir . '/test parser';
        TestHelper::createMockExecutable($validExecutable);

        $executor = new Executor($validExecutable);
        $this->assertEquals($validExecutable, $executor->getExecutablePath());
    }
}
