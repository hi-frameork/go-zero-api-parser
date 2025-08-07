<?php

declare(strict_types=1);

namespace GoZeroApiParser;

/**
 * go-zero API 解析结果处理器
 * 负责解析和提取 Go 程序输出的 JSON 结果
 */
class ApiParser
{
    private Executor $executor;

    /**
     * @param Executor|null $executor 可执行文件执行器，为 null 时自动创建
     */
    public function __construct(?Executor $executor = null)
    {
        $this->executor = $executor ?? new Executor;
    }

    /**
     * 解析 API 文件内容
     *
     * @param string $apiFilePath API 文件路径
     *
     * @return array 解析后的结构化数据
     *
     * @throws \RuntimeException 当解析失败时
     */
    public function parseFile(string $apiFilePath): array
    {
        $jsonOutput = $this->executor->execute($apiFilePath);
        return $this->parseJsonOutput($jsonOutput);
    }

    /**
     * 解析 API 文件并返回 JSON 字符串
     *
     * @param string $apiFilePath API 文件路径
     * @param bool   $prettyPrint 是否格式化 JSON 输出
     *
     * @return string JSON 格式的解析结果
     *
     * @throws \RuntimeException 当解析失败时
     */
    public function parseFileToJson(string $apiFilePath, bool $prettyPrint = true): string
    {
        $result = $this->parseFile($apiFilePath);

        $flags = \JSON_UNESCAPED_UNICODE;
        if ($prettyPrint) {
            $flags |= \JSON_PRETTY_PRINT;
        }

        return \json_encode($result, $flags);
    }

    /**
     * 批量解析多个 API 文件
     *
     * @param array $apiFilePaths API 文件路径数组
     *
     * @return array 解析结果数组，键为文件路径，值为解析结果
     */
    public function parseMultipleFiles(array $apiFilePaths): array
    {
        $executionResults = $this->executor->executeMultiple($apiFilePaths);
        $results = [];

        foreach ($executionResults as $filePath => $executionResult) {
            if ($executionResult['success']) {
                try {
                    $results[$filePath] = $this->parseJsonOutput($executionResult['output']);
                } catch (\Exception $e) {
                    $results[$filePath] = [
                        'error' => 'JSON 解析失败: ' . $e->getMessage(),
                    ];
                }
            } else {
                $results[$filePath] = [
                    'error' => $executionResult['error'],
                ];
            }
        }

        return $results;
    }

    /**
     * 获取执行器实例
     *
     * @return Executor 执行器实例
     */
    public function getExecutor(): Executor
    {
        return $this->executor;
    }

    /**
     * 设置执行器实例
     *
     * @param Executor $executor 新的执行器实例
     */
    public function setExecutor(Executor $executor): void
    {
        $this->executor = $executor;
    }

    /**
     * 解析 JSON 输出
     *
     * @param string $jsonOutput 原始 JSON 输出
     *
     * @return array 解析后的数组
     *
     * @throws \RuntimeException 当 JSON 解析失败时
     */
    private function parseJsonOutput(string $jsonOutput): array
    {
        $result = \json_decode($jsonOutput, true);

        if (\JSON_ERROR_NONE !== \json_last_error()) {
            throw new \RuntimeException('JSON 解析失败: ' . \json_last_error_msg() . "\n原始输出: " . $jsonOutput);
        }

        return $result;
    }

    /**
     * 获取解析结果中的基本信息
     *
     * @param string $apiFilePath API 文件路径
     *
     * @return array 包含 syntax, info, imports 的基本信息
     */
    public function getInfo(string $apiFilePath): array
    {
        $result = $this->parseFile($apiFilePath);
        return $result['Info'] ?? [];
    }

    /**
     * 获取解析结果中的语法信息
     *
     * @param string $apiFilePath API 文件路径
     *
     * @return array 语法信息数组
     */
    public function getSyntax(string $apiFilePath): array
    {
        $result = $this->parseFile($apiFilePath);
        return $result['Syntax'] ?? [];
    }

    /**
     * 获取解析结果中的导入信息
     *
     * @param string $apiFilePath API 文件路径
     *
     * @return array 导入信息数组
     */
    public function getImports(string $apiFilePath): array
    {
        $result = $this->parseFile($apiFilePath);
        return $result['Imports'] ?? [];
    }

    /**
     * 获取解析结果中的类型定义
     *
     * @param string $apiFilePath API 文件路径
     *
     * @return array 类型定义数组
     */
    public function getTypes(string $apiFilePath): array
    {
        $result = $this->parseFile($apiFilePath);
        return $result['Types'] ?? [];
    }

    /**
     * 获取解析结果中的服务信息
     *
     * @param string $apiFilePath API 文件路径
     *
     * @return array 服务信息数组
     */
    public function getService(string $apiFilePath): array
    {
        $result = $this->parseFile($apiFilePath);
        return $result['Service'] ?? [];
    }

    /**
     * 获取解析结果中的路由信息
     *
     * @param string $apiFilePath API 文件路径
     *
     * @return array 路由信息数组
     */
    public function getGroups(string $apiFilePath): array
    {
        $result = $this->parseFile($apiFilePath);
        return $result['Service']['Groups'] ?? [];
    }
}
