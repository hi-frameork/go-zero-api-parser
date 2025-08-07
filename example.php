<?php

use GoZeroApiParser\ApiParser;

require_once 'vendor/autoload.php';

try {
    // 创建解析器实例（自动检测运行环境）
    $parser = new ApiParser();

    // 显示检测到的可执行文件信息
    echo "=== 环境检测结果 ===\n";
    echo '使用的可执行文件: ' . $parser->getExecutor()->getExecutablePath() . "\n";
    echo '是否自动检测: ' . ($parser->getExecutor()->isAutoDetected() ? '是' : '否') . "\n\n";

    // 解析 admin.api 文件
    $apiFile = 'doc/admin.api';

    echo "=== 解析 API 文件：{$apiFile} ===\n\n";

    // 1. 获取完整的解析结果
    echo "1. 完整解析结果：\n";
    $result = $parser->parseFile($apiFile);
    echo \json_encode($result, \JSON_PRETTY_PRINT | \JSON_UNESCAPED_UNICODE) . "\n\n";

    // 2. 获取基本信息
    echo "2. 基本信息：\n";
    $basicInfo = $parser->getInfo($apiFile);
    echo \json_encode($basicInfo, \JSON_PRETTY_PRINT | \JSON_UNESCAPED_UNICODE) . "\n\n";

    // 3. 获取服务信息
    echo "3. 服务信息：\n";
    $result = $parser->parseFile($apiFile);
    if (isset($result['Service'])) {
        echo '服务名称: ' . ($result['Service']['Name'] ?? '') . "\n";
        if (isset($result['Service']['Groups'])) {
            foreach ($result['Service']['Groups'] as $group) {
                echo '路由前缀: ' . ($group['Annotation']['Properties']['prefix'] ?? '') . "\n";
                echo '路由组: ' . ($group['Annotation']['Properties']['group'] ?? '') . "\n";
                echo '路由数量: ' . \count($group['Routes'] ?? []) . "\n\n";
            }
        }
    }

    // 4. 获取所有路由
    echo "4. 路由信息：\n";

    // 5. 获取类型定义
    echo "5. 类型定义：\n";
    $types = $parser->getTypes($apiFile);
    if (empty($types)) {
        echo "没有在当前文件中找到类型定义（可能在导入的文件中）\n";
    } else {
        foreach ($types as $type) {
            echo '类型名称: ' . ($type['RawName'] ?? 'N/A') . "\n";
            echo '字段数量: ' . \count($type['Members'] ?? []) . "\n";
            if (isset($type['Members']) && \is_array($type['Members'])) {
                foreach ($type['Members'] as $field) {
                    echo "  - {$field['Name']}: {$field['Type']['RawName']}"
                         . (\str_contains($field['Tag'] ?? '', 'optional') ? ' (可选)' : '') . "\n";
                }
            }
            echo "\n";
        }
    }

    // 6. 演示错误处理
    echo "6. 错误处理演示：\n";

    try {
        $parser->parseFile('non-existent-file.api');
    } catch (Exception $e) {
        echo '捕获到错误: ' . $e->getMessage() . "\n";
    }

} catch (Exception $e) {
    echo '错误: ' . $e->getMessage() . "\n";
    exit(1);
}
