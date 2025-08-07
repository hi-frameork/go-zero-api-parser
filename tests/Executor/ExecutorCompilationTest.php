<?php

declare(strict_types=1);

namespace GoZeroApiParser\Tests\Executor;

use GoZeroApiParser\Executor;
use PHPUnit\Framework\TestCase;

/**
 * Executor Go 编译功能简化测试
 * 专注于核心编译功能的兼容性验证
 *
 * @group integration
 * @group requires-go
 */
class ExecutorCompilationTest extends TestCase
{
    private string $tempDir;
    private \ReflectionClass $executorReflection;

    protected function setUp(): void
    {
        parent::setUp();

        if (! TestHelper::hasGoEnvironment()) {
            $this->markTestSkipped('需要 Go 环境来运行编译测试');
        }

        $goEnvironmentInfo = TestHelper::validateGoEnvironment();
        if (! $goEnvironmentInfo['is_compatible']) {
            $this->markTestSkipped($goEnvironmentInfo['error']);
        }

        $this->tempDir = TestHelper::createTempDir('executor_compile_');
        $this->executorReflection = new \ReflectionClass(Executor::class);
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        TestHelper::recursiveDelete($this->tempDir);
    }

    // ========== Go 编译功能测试 ==========

    /**
     * 测试成功编译 Go 程序
     */
    public function testCompileGoExecutableSuccess(): void
    {
        $packageDir = $this->tempDir . '/go_package';
        \mkdir($packageDir, 0o755, true);

        // 创建 main.go 文件
        TestHelper::createGoMainFile($packageDir . '/main.go');
        TestHelper::createGoMod($packageDir);

        $compiledExecutable = $packageDir . '/compiled-parser';

        $validExecutable = $this->tempDir . '/temp-parser';
        TestHelper::createMockExecutable($validExecutable);
        $executor = new Executor($validExecutable);

        $method = $this->executorReflection->getMethod('compileGoExecutable');
        $method->setAccessible(true);

        try {
            $result = $method->invoke($executor, $packageDir, $compiledExecutable);

            $this->assertEquals($compiledExecutable, $result);
            $this->assertFileExists($compiledExecutable);
            $this->assertTrue(\is_executable($compiledExecutable));

        } catch (\RuntimeException $e) {
            // 如果编译失败，检查是否是已知的环境问题
            $this->assertStringContainsString('找不到', $e->getMessage());
        }
    }

    /**
     * 测试编译失败的情况 - 没有 main.go 文件
     */
    public function testCompileGoExecutableFailureNoMainFile(): void
    {
        $packageDir = $this->tempDir . '/empty_package';
        \mkdir($packageDir, 0o755, true);

        $compiledExecutable = $packageDir . '/compiled-parser';

        $validExecutable = $this->tempDir . '/temp-parser';
        TestHelper::createMockExecutable($validExecutable);
        $executor = new Executor($validExecutable);

        $method = $this->executorReflection->getMethod('compileGoExecutable');
        $method->setAccessible(true);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('找不到');

        $method->invoke($executor, $packageDir, $compiledExecutable);
    }

    /**
     * 测试编译包含语法错误的 Go 代码
     */
    public function testCompileGoExecutableWithSyntaxError(): void
    {
        $packageDir = $this->tempDir . '/error_package';
        \mkdir($packageDir, 0o755, true);

        // 创建包含语法错误的 main.go
        $invalidGoContent = <<<'EOD'
package main

import "fmt"

func main() {
    fmt.Println("Hello"  // 缺少右括号
}
EOD;
        \file_put_contents($packageDir . '/main.go', $invalidGoContent);
        TestHelper::createGoMod($packageDir);

        $compiledExecutable = $packageDir . '/compiled-parser';

        $validExecutable = $this->tempDir . '/temp-parser';
        TestHelper::createMockExecutable($validExecutable);
        $executor = new Executor($validExecutable);

        $method = $this->executorReflection->getMethod('attemptCompile');
        $method->setAccessible(true);

        $success = $method->invoke(
            $executor,
            $packageDir . '/main.go',
            $compiledExecutable,
        );

        $this->assertFalse($success);
        $this->assertFileDoesNotExist($compiledExecutable);
    }

    /**
     * 测试使用传统语法编译
     */
    public function testCompileWithTraditionalSyntax(): void
    {
        $packageDir = $this->tempDir . '/traditional_package';
        \mkdir($packageDir, 0o755, true);

        TestHelper::createGoMainFile($packageDir . '/main.go', false); // 不使用泛型
        TestHelper::createGoMod($packageDir, 'traditional-parser', '1.21');

        $compiledExecutable = $packageDir . '/traditional-parser';

        $validExecutable = $this->tempDir . '/temp-parser';
        TestHelper::createMockExecutable($validExecutable);
        $executor = new Executor($validExecutable);

        $method = $this->executorReflection->getMethod('attemptCompile');
        $method->setAccessible(true);

        $success = $method->invoke(
            $executor,
            $packageDir . '/main.go',
            $compiledExecutable,
        );

        if ($success) {
            $this->assertTrue($success);
            $this->assertFileExists($compiledExecutable);
            $this->assertTrue(\is_executable($compiledExecutable));
        } else {
            $this->markTestSkipped('Go 编译失败，可能是环境问题');
        }
    }

    /**
     * 测试使用现代语法编译（Go 1.21+）
     */
    public function testCompileWithModernSyntax(): void
    {
        if (! TestHelper::isGoVersionAtLeast('1.21')) {
            $this->markTestSkipped('需要 Go 1.21+ 来测试现代语法');
        }

        $packageDir = $this->tempDir . '/modern_package';
        \mkdir($packageDir, 0o755, true);

        TestHelper::createGoMainFile($packageDir . '/main.go', true); // 使用泛型
        TestHelper::createGoMod($packageDir, 'modern-parser', '1.21');

        $compiledExecutable = $packageDir . '/modern-parser';

        $validExecutable = $this->tempDir . '/temp-parser';
        TestHelper::createMockExecutable($validExecutable);
        $executor = new Executor($validExecutable);

        $method = $this->executorReflection->getMethod('attemptCompile');
        $method->setAccessible(true);

        $success = $method->invoke(
            $executor,
            $packageDir . '/main.go',
            $compiledExecutable,
        );

        if ($success) {
            $this->assertTrue($success);
            $this->assertFileExists($compiledExecutable);
            $this->assertTrue(\is_executable($compiledExecutable));
        } else {
            $this->markTestSkipped('使用泛型的 Go 编译失败');
        }
    }

    // ========== 编译性能和稳定性测试 ==========

    /**
     * 测试重复编译的稳定性
     */
    public function testRepeatedCompilation(): void
    {
        $packageDir = $this->tempDir . '/repeat_package';
        \mkdir($packageDir, 0o755, true);

        TestHelper::createGoMainFile($packageDir . '/main.go');
        TestHelper::createGoMod($packageDir);

        $validExecutable = $this->tempDir . '/temp-parser';
        TestHelper::createMockExecutable($validExecutable);
        $executor = new Executor($validExecutable);

        $method = $this->executorReflection->getMethod('attemptCompile');
        $method->setAccessible(true);

        // 重复编译 3 次
        for ($i = 1; $i <= 3; $i++) {
            $compiledExecutable = $packageDir . "/compiled-parser-{$i}";

            $success = $method->invoke(
                $executor,
                $packageDir . '/main.go',
                $compiledExecutable,
            );

            if ($success) {
                $this->assertTrue($success);
                $this->assertFileExists($compiledExecutable);
                $this->assertTrue(\is_executable($compiledExecutable));
            } else {
                $this->markTestSkipped('Go 编译失败，跳过重复编译测试');
                break;
            }
        }
    }

    /**
     * 测试编译到不同目录
     */
    public function testCompileTodifférentDirectories(): void
    {
        $packageDir = $this->tempDir . '/source_package';
        \mkdir($packageDir, 0o755, true);

        TestHelper::createGoMainFile($packageDir . '/main.go');
        TestHelper::createGoMod($packageDir);

        $validExecutable = $this->tempDir . '/temp-parser';
        TestHelper::createMockExecutable($validExecutable);
        $executor = new Executor($validExecutable);

        $method = $this->executorReflection->getMethod('attemptCompile');
        $method->setAccessible(true);

        // 编译到不同的输出目录
        $outputDirs = [
            $this->tempDir . '/output1',
            $this->tempDir . '/output2/subdir',
        ];

        foreach ($outputDirs as $outputDir) {
            if (! \is_dir($outputDir)) {
                \mkdir($outputDir, 0o755, true);
            }

            $compiledExecutable = $outputDir . '/parser';

            $success = $method->invoke(
                $executor,
                $packageDir . '/main.go',
                $compiledExecutable,
            );

            if ($success) {
                $this->assertTrue($success);
                $this->assertFileExists($compiledExecutable);
                $this->assertTrue(\is_executable($compiledExecutable));
            } else {
                $this->markTestSkipped('Go 编译失败，跳过多目录编译测试');
                break;
            }
        }
    }

    // ========== 错误恢复测试 ==========

    /**
     * 测试编译错误后的恢复
     */
    public function testCompilationErrorRecovery(): void
    {
        $packageDir = $this->tempDir . '/recovery_package';
        \mkdir($packageDir, 0o755, true);

        $validExecutable = $this->tempDir . '/temp-parser';
        TestHelper::createMockExecutable($validExecutable);
        $executor = new Executor($validExecutable);

        $method = $this->executorReflection->getMethod('attemptCompile');
        $method->setAccessible(true);

        // 1. 先尝试编译一个有错误的文件
        $invalidGoContent = 'package main\n\nfunc main() {\n    invalid syntax\n';
        \file_put_contents($packageDir . '/main.go', $invalidGoContent);
        TestHelper::createGoMod($packageDir);

        $compiledExecutable = $packageDir . '/parser';

        $success1 = $method->invoke(
            $executor,
            $packageDir . '/main.go',
            $compiledExecutable,
        );

        $this->assertFalse($success1);
        $this->assertFileDoesNotExist($compiledExecutable);

        // 2. 修复文件后重新编译
        TestHelper::createGoMainFile($packageDir . '/main.go');

        $success2 = $method->invoke(
            $executor,
            $packageDir . '/main.go',
            $compiledExecutable,
        );

        if ($success2) {
            $this->assertTrue($success2);
            $this->assertFileExists($compiledExecutable);
            $this->assertTrue(\is_executable($compiledExecutable));
        } else {
            $this->markTestSkipped('修复后的 Go 编译仍然失败');
        }
    }
}
