<?php

// 直接包含我们的 ApiParser 类
require_once 'src/ApiParser.php';

use GoZeroApiParser\ApiParser;

try {
    // 创建解析器实例
    $parser = new ApiParser('./api-parser');
    
    // 解析 admin.api 文件
    $apiFile = 'doc/admin.api';
    
    echo "=== 解析 API 文件：{$apiFile} ===\n\n";
    
    // 1. 获取完整的解析结果
    echo "1. 完整解析结果：\n";
    $result = $parser->parseFile($apiFile);
    echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n\n";
    
    // 2. 获取基本信息
    echo "2. 基本信息：\n";
    $basicInfo = $parser->getBasicInfo($apiFile);
    echo json_encode($basicInfo, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n\n";
    
    // 3. 获取服务信息
    echo "3. 服务信息：\n";
    $services = $parser->getServices($apiFile);
    foreach ($services as $service) {
        echo "服务名称: " . $service['name'] . "\n";
        echo "路由前缀: " . ($service['server']['prefix'] ?? '') . "\n";
        echo "路由组: " . ($service['server']['group'] ?? '') . "\n";
        echo "路由数量: " . count($service['routes']) . "\n\n";
    }
    
    // 4. 获取所有路由
    echo "4. 路由信息：\n";
    $routes = $parser->getRoutes($apiFile);
    foreach ($routes as $route) {
        echo sprintf(
            "%-15s %-30s %-20s -> %-20s [%s]\n",
            strtoupper($route['method']),
            $route['path'],
            $route['request_type'] ?? 'N/A',
            $route['response_type'] ?? 'N/A',
            $route['handler']
        );
    }
    echo "\n";
    
    // 5. 获取类型定义
    echo "5. 类型定义：\n";
    $types = $parser->getTypes($apiFile);
    if (empty($types)) {
        echo "没有在当前文件中找到类型定义（可能在导入的文件中）\n\n";
    } else {
        foreach ($types as $type) {
            echo "类型名称: " . $type['name'] . "\n";
            echo "字段数量: " . count($type['fields']) . "\n";
            foreach ($type['fields'] as $field) {
                echo "  - {$field['name']}: {$field['type']}" . 
                     ($field['optional'] ? ' (可选)' : '') . "\n";
            }
            echo "\n";
        }
    }
    
    // 6. 演示错误处理
    echo "6. 错误处理演示：\n";
    try {
        $parser->parseFile('non-existent-file.api');
    } catch (Exception $e) {
        echo "捕获到错误: " . $e->getMessage() . "\n\n";
    }
    
    echo "=== 解析完成 ===\n";
    
} catch (Exception $e) {
    echo "错误: " . $e->getMessage() . "\n";
    exit(1);
}
