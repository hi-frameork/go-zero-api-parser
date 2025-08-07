<?php

declare(strict_types=1);

namespace GoZeroApiParser;

/**
 * Go 可执行文件执行器
 * 负责环境检测、可执行文件管理和命令执行
 */
class Executor
{
    private string $executablePath;
    private bool $autoDetected = false;

    /**
     * @param string|null $executablePath Go 可执行文件的路径，为 null 时自动检测环境
     */
    public function __construct(?string $executablePath = null)
    {
        if (null === $executablePath) {
            $this->executablePath = $this->autoDetectExecutable();
            $this->autoDetected = true;
        } else {
            $this->executablePath = $executablePath;
            $this->validateExecutable($this->executablePath);
        }
    }

    /**
     * 执行 API 文件解析命令
     *
     * @param string $apiFilePath API 文件路径
     *
     * @return string 原始的 JSON 输出
     *
     * @throws \RuntimeException 当执行失败时
     */
    public function execute(string $apiFilePath): string
    {
        if (! \file_exists($apiFilePath)) {
            throw new \InvalidArgumentException("API 文件不存在: {$apiFilePath}");
        }

        // 构建命令
        $command = \sprintf('%s %s 2>&1', \escapeshellarg($this->executablePath), \escapeshellarg($apiFilePath));

        // 执行命令
        $output = [];
        $returnCode = 0;
        \exec($command, $output, $returnCode);

        if (0 !== $returnCode) {
            throw new \RuntimeException('API 解析执行失败: ' . \implode("\n", $output));
        }

        return \implode("\n", $output);
    }

    /**
     * 批量执行多个 API 文件解析
     *
     * @param array $apiFilePaths API 文件路径数组
     *
     * @return array 执行结果数组，键为文件路径，值为原始 JSON 输出或错误信息
     */
    public function executeMultiple(array $apiFilePaths): array
    {
        $results = [];

        foreach ($apiFilePaths as $filePath) {
            try {
                $results[$filePath] = [
                    'success' => true,
                    'output' => $this->execute($filePath),
                ];
            } catch (\Exception $e) {
                $results[$filePath] = [
                    'success' => false,
                    'error' => $e->getMessage(),
                ];
            }
        }

        return $results;
    }

    /**
     * 设置可执行文件路径
     *
     * @param string $executablePath 新的可执行文件路径
     */
    public function setExecutablePath(string $executablePath): void
    {
        $this->validateExecutable($executablePath);
        $this->executablePath = $executablePath;
        $this->autoDetected = false;
    }

    /**
     * 获取当前可执行文件路径
     *
     * @return string 可执行文件路径
     */
    public function getExecutablePath(): string
    {
        return $this->executablePath;
    }

    /**
     * 检查是否为自动检测的可执行文件
     *
     * @return bool 是否为自动检测
     */
    public function isAutoDetected(): bool
    {
        return $this->autoDetected;
    }

    /**
     * 获取系统信息
     *
     * @return array 系统信息数组
     */
    public function getSystemInfo(): array
    {
        $goVersion = null;
        $output = [];
        $returnCode = 0;
        \exec('go version 2>/dev/null', $output, $returnCode);
        if (0 === $returnCode && ! empty($output)) {
            $goVersion = \implode(' ', $output);
        }

        return [
            'os' => \php_uname('s'),
            'arch' => \php_uname('m'),
            'php_version' => \PHP_VERSION,
            'go_version' => $goVersion,
            'go_available' => $this->hasGoEnvironment(),
        ];
    }

    /**
     * 自动检测并准备可执行文件
     *
     * @return string 可执行文件路径
     *
     * @throws \RuntimeException 当无法准备可执行文件时
     */
    protected function autoDetectExecutable(): string
    {
        $packageDir = $this->getPackageDirectory();

        // 1. 检查是否为 macOS ARM 芯片
        if ($this->isMacOSARM()) {
            $armExecutable = $packageDir . '/api-parser-macos-arm64';
            if (\file_exists($armExecutable) && \is_executable($armExecutable)) {
                return $armExecutable;
            }
        }

        // 3. 检查是否有自动编译的可执行文件
        $compiledExecutable = $packageDir . '/api-parser-compiled';
        if (\file_exists($compiledExecutable) && \is_executable($compiledExecutable)) {
            return $compiledExecutable;
        }

        // 4. 检查 Go 环境并尝试编译
        if ($this->hasGoEnvironment()) {
            return $this->compileGoExecutable($packageDir, $compiledExecutable);
        }

        // 5. 提示安装 Go 环境
        throw new \RuntimeException($this->getGoInstallationMessage());
    }

    /**
     * 验证可执行文件是否存在且可执行
     *
     * @param string $executablePath 可执行文件路径
     *
     * @throws \InvalidArgumentException 当文件不存在或不可执行时
     */
    private function validateExecutable(string $executablePath): void
    {
        if (! \file_exists($executablePath)) {
            throw new \InvalidArgumentException("API 解析器可执行文件不存在: {$executablePath}");
        }

        if (! \is_executable($executablePath)) {
            throw new \InvalidArgumentException("API 解析器文件不可执行: {$executablePath}");
        }
    }

    /**
     * 检查是否为 macOS ARM 芯片
     *
     * @return bool 是否为 macOS ARM
     */
    private function isMacOSARM(): bool
    {
        // 检查操作系统
        if ('Darwin' !== \php_uname('s')) {
            return false;
        }

        // 检查 CPU 架构
        return \in_array(\php_uname('m'), ['arm64', 'aarch64']);
    }

    /**
     * 检查是否有 Go 编译环境
     *
     * @return bool 是否有 Go 环境
     */
    protected function hasGoEnvironment(): bool
    {
        $output = [];
        $returnCode = 0;
        \exec('go version 2>/dev/null', $output, $returnCode);

        return 0 === $returnCode && ! empty($output);
    }

    /**
     * 编译 Go 可执行文件
     *
     * @param string $baseDir 基础目录
     *
     * @return string 编译后的可执行文件路径
     *
     * @throws \RuntimeException 当编译失败时
     */
    private function compileGoExecutable(string $baseDir, string $compiledExecutable): string
    {
        // 尝试从包目录编译
        $packageMainGo = $baseDir . '/go/main.go';
        if (\file_exists($packageMainGo)) {
            $success = $this->attemptCompile($packageMainGo, $compiledExecutable);
            if ($success) {
                return $compiledExecutable;
            }
        }

        throw new \RuntimeException("找不到 {$packageMainGo} 文件，无法编译。请确保 main.go 存在于包目录或项目根目录中。");
    }

    /**
     * 尝试编译 Go 文件
     *
     * @param string $mainGoPath     main.go 文件路径
     * @param string $executableName 输出的可执行文件路径
     *
     * @return bool 编译是否成功
     */
    private function attemptCompile(string $mainGoPath, string $executableName): bool
    {
        $command = \sprintf(
            'cd %s && go build -mod=readonly -o %s %s 2>&1',
            \escapeshellarg(\dirname(\realpath($mainGoPath))),
            \escapeshellarg($executableName),
            \escapeshellarg(\basename($mainGoPath)),
        );

        $output = [];
        $returnCode = 0;
        \exec($command, $output, $returnCode);

        if (0 !== $returnCode) {
            // 编译失败，但不抛出异常，让调用方决定
            return false;
        }

        return \file_exists($executableName) && \is_executable($executableName);
    }

    /**
     * 获取包的根目录
     *
     * @return string 包根目录路径
     */
    private function getPackageDirectory(): string
    {
        // 通过当前文件的位置计算包根目录
        // 当前文件是 src/Executor.php，包根目录是上一级
        return \dirname(__DIR__);
    }

    /**
     * 获取路径调试信息
     *
     * @return array 路径信息数组
     */
    public function getPathInfo(): array
    {
        $packageDir = $this->getPackageDirectory();

        return [
            'current_file' => __FILE__,
            'package_directory' => $packageDir,
            'working_directory' => \getcwd(),
            'possible_executables' => [
                'arm64_package' => $packageDir . '/api-parser-macos-arm64',
                'default_package' => $packageDir . '/api-parser',
                'arm64_current' => './api-parser-macos-arm64',
                'default_current' => './api-parser',
            ],
            'file_existence' => [
                'composer_json' => \file_exists($packageDir . '/composer.json'),
                'main_go_package' => \file_exists($packageDir . '/go/main.go'),
                'main_go_current' => \file_exists('./main.go'),
                'arm64_package' => \file_exists($packageDir . '/api-parser-macos-arm64'),
                'default_package' => \file_exists($packageDir . '/api-parser'),
                'arm64_current' => \file_exists('./api-parser-macos-arm64'),
                'default_current' => \file_exists('./api-parser'),
            ],
        ];
    }

    /**
     * 获取 Go 安装提示信息
     *
     * @return string 安装提示信息
     */
    protected function getGoInstallationMessage(): string
    {
        $os = \php_uname('s');
        $arch = \php_uname('m');

        $message = "未找到可用的 API 解析器，请安装 Go 语言环境后重试。\n\n";
        $message .= "当前系统: {$os} {$arch}\n\n";
        $message .= "Go 语言安装指南:\n";

        switch ($os) {
            case 'Darwin': // macOS
                $message .= "macOS 安装方式:\n";
                $message .= "1. 使用 Homebrew: brew install go\n";
                $message .= "2. 从官网下载: https://golang.org/dl/\n";
                $message .= "3. 使用 MacPorts: sudo port install go\n";
                break;

            case 'Linux':
                $message .= "Linux 安装方式:\n";
                $message .= "1. Ubuntu/Debian: sudo apt-get install golang\n";
                $message .= "2. CentOS/RHEL: sudo yum install golang 或 sudo dnf install golang\n";
                $message .= "3. Arch Linux: sudo pacman -S go\n";
                $message .= "4. 从官网下载: https://golang.org/dl/\n";
                break;

            case 'WINNT': // Windows
                $message .= "Windows 安装方式:\n";
                $message .= "1. 从官网下载安装程序: https://golang.org/dl/\n";
                $message .= "2. 使用 Chocolatey: choco install golang\n";
                $message .= "3. 使用 Scoop: scoop install go\n";
                break;

            default:
                $message .= "请访问 Go 语言官网下载适合您系统的版本: https://golang.org/dl/\n";
                break;
        }

        $message .= "\n安装完成后，请重新运行程序。";

        return $message;
    }
}
