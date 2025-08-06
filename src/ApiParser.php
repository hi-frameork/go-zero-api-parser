<?php

declare(strict_types=1);

namespace GoZeroApiParser;

use InvalidArgumentException;
use RuntimeException;

/**
 * go-zero API 文件解析器
 * 通过调用 Go 可执行文件来解析 .api 文件并返回 JSON 格式的结构化数据
 */
class ApiParser
{
    private string $executablePath;

    /**
     * @param string $executablePath Go 可执行文件的路径
     */
    public function __construct(string $executablePath = './api-parser')
    {
        $this->executablePath = $executablePath;
        
        if (!file_exists($this->executablePath)) {
            throw new InvalidArgumentException("API 解析器可执行文件不存在: {$this->executablePath}");
        }
        
        if (!is_executable($this->executablePath)) {
            throw new InvalidArgumentException("API 解析器文件不可执行: {$this->executablePath}");
        }
    }

    /**
     * 解析 API 文件内容
     * 
     * @param string $apiFilePath API 文件路径
     * @return array 解析后的结构化数据
     * @throws RuntimeException 当解析失败时
     */
    public function parseFile(string $apiFilePath): array
    {
        if (!file_exists($apiFilePath)) {
            throw new InvalidArgumentException("API 文件不存在: {$apiFilePath}");
        }

        // 构建命令
        $command = sprintf('%s %s 2>&1', escapeshellarg($this->executablePath), escapeshellarg($apiFilePath));
        
        // 执行命令
        $output = [];
        $returnCode = 0;
        exec($command, $output, $returnCode);
        
        if ($returnCode !== 0) {
            throw new RuntimeException("API 解析失败: " . implode("\n", $output));
        }
        
        $jsonOutput = implode("\n", $output);
        $result = json_decode($jsonOutput, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new RuntimeException("JSON 解析失败: " . json_last_error_msg() . "\n原始输出: " . $jsonOutput);
        }
        
        return $result;
    }

    /**
     * 解析 API 文件并返回 JSON 字符串
     * 
     * @param string $apiFilePath API 文件路径
     * @param bool $prettyPrint 是否格式化 JSON 输出
     * @return string JSON 格式的解析结果
     * @throws RuntimeException 当解析失败时
     */
    public function parseFileToJson(string $apiFilePath, bool $prettyPrint = true): string
    {
        $result = $this->parseFile($apiFilePath);
        
        $flags = JSON_UNESCAPED_UNICODE;
        if ($prettyPrint) {
            $flags |= JSON_PRETTY_PRINT;
        }
        
        return json_encode($result, $flags);
    }

    /**
     * 获取解析结果中的服务信息
     * 
     * @param string $apiFilePath API 文件路径
     * @return array 服务信息数组
     */
    public function getServices(string $apiFilePath): array
    {
        $result = $this->parseFile($apiFilePath);
        return $result['services'] ?? [];
    }

    /**
     * 获取解析结果中的类型定义
     * 
     * @param string $apiFilePath API 文件路径
     * @return array 类型定义数组
     */
    public function getTypes(string $apiFilePath): array
    {
        $result = $this->parseFile($apiFilePath);
        return $result['types'] ?? [];
    }

    /**
     * 获取解析结果中的路由信息
     * 
     * @param string $apiFilePath API 文件路径
     * @return array 路由信息数组
     */
    public function getRoutes(string $apiFilePath): array
    {
        $services = $this->getServices($apiFilePath);
        $routes = [];
        
        foreach ($services as $service) {
            if (isset($service['routes']) && is_array($service['routes'])) {
                foreach ($service['routes'] as $route) {
                    $routes[] = array_merge($route, [
                        'service_name' => $service['name'] ?? '',
                        'service_server' => $service['server'] ?? []
                    ]);
                }
            }
        }
        
        return $routes;
    }

    /**
     * 获取解析结果中的基本信息
     * 
     * @param string $apiFilePath API 文件路径
     * @return array 包含 syntax, info, imports 的基本信息
     */
    public function getBasicInfo(string $apiFilePath): array
    {
        $result = $this->parseFile($apiFilePath);
        
        return [
            'syntax' => $result['syntax'] ?? '',
            'info' => $result['info'] ?? [],
            'imports' => $result['imports'] ?? []
        ];
    }

    /**
     * 批量解析多个 API 文件
     * 
     * @param array $apiFilePaths API 文件路径数组
     * @return array 解析结果数组，键为文件路径，值为解析结果
     */
    public function parseMultipleFiles(array $apiFilePaths): array
    {
        $results = [];
        
        foreach ($apiFilePaths as $filePath) {
            try {
                $results[$filePath] = $this->parseFile($filePath);
            } catch (\Exception $e) {
                $results[$filePath] = [
                    'error' => $e->getMessage()
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
        if (!file_exists($executablePath)) {
            throw new InvalidArgumentException("API 解析器可执行文件不存在: {$executablePath}");
        }
        
        if (!is_executable($executablePath)) {
            throw new InvalidArgumentException("API 解析器文件不可执行: {$executablePath}");
        }
        
        $this->executablePath = $executablePath;
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
}