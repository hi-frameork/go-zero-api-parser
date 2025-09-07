<?php

require_once 'vendor/autoload.php';

use GoZeroApiParser\ApiParser;

$parser = new ApiParser();

// 获取所有API文件
$apiFiles = glob('doc/*.api');
$moduleFiles = glob('doc/modules/*/*.api');
$allFiles = array_merge($apiFiles, $moduleFiles);

echo "=== Go-Zero API 解析器测试 ===\n";
echo "发现 " . count($allFiles) . " 个API文件\n\n";

$successCount = 0;
$failedCount = 0;

foreach ($allFiles as $file) {
    echo "📄 解析文件: $file\n";
    echo str_repeat('-', 50) . "\n";
    
    try {
        $info = $parser->getInfo($file);
        $types = $parser->getTypes($file);
        $service = $parser->getService($file);
        $groups = $parser->getGroups($file);
        
        echo "✅ 解析成功\n";
        echo "📋 标题: " . ($info['Title'] ?? 'N/A') . "\n";
        echo "📋 描述: " . ($info['Desc'] ?? 'N/A') . "\n";
        echo "📋 版本: " . ($info['Version'] ?? 'N/A') . "\n";
        echo "📋 作者: " . ($info['Author'] ?? 'N/A') . "\n";
        echo "🏗️  类型数量: " . count($types) . "\n";
        echo "🔧 服务名称: " . ($service['Name'] ?? 'N/A') . "\n";
        
        $totalRoutes = 0;
        foreach ($groups as $group) {
            $totalRoutes += count($group['Routes'] ?? []);
        }
        echo "🚀 路由数量: $totalRoutes\n";
        
        // 显示类型信息
        if (!empty($types)) {
            echo "\n📝 类型定义:\n";
            foreach (array_slice($types, 0, 3) as $type) {
                $memberCount = count($type['Members'] ?? []);
                echo "   • " . ($type['RawName'] ?? 'N/A') . " ($memberCount 个字段)\n";
            }
            if (count($types) > 3) {
                echo "   ... 还有 " . (count($types) - 3) . " 个类型\n";
            }
        }
        
        // 显示路由信息
        if (!empty($groups)) {
            echo "\n🌐 API路由:\n";
            $routeCount = 0;
            foreach ($groups as $group) {
                foreach ($group['Routes'] ?? [] as $route) {
                    if ($routeCount < 3) {
                        $method = strtoupper($route['Method'] ?? 'N/A');
                        $path = $route['Path'] ?? 'N/A';
                        $handler = $route['Handler'] ?? 'N/A';
                        echo "   • $method $path [$handler]\n";
                        $routeCount++;
                    }
                }
            }
            if ($totalRoutes > 3) {
                echo "   ... 还有 " . ($totalRoutes - 3) . " 个路由\n";
            }
        }
        
        $successCount++;
        
    } catch (Exception $e) {
        echo "❌ 解析失败: " . $e->getMessage() . "\n";
        $failedCount++;
    }
    
    echo "\n" . str_repeat('=', 60) . "\n\n";
}

echo "📊 解析结果统计:\n";
echo "✅ 成功: $successCount 个文件\n";
echo "❌ 失败: $failedCount 个文件\n";
echo "📁 总计: " . count($allFiles) . " 个文件\n";

if ($successCount > 0) {
    echo "\n🎉 API解析器工作正常！\n";
    echo "\n💡 提示: 您可以使用以下方法解析API文件：\n";
    echo "   \$parser = new ApiParser();\n";
    echo "   \$result = \$parser->parseFile('doc/simple.api');\n";
    echo "   \$info = \$parser->getInfo('doc/simple.api');\n";
    echo "   \$types = \$parser->getTypes('doc/simple.api');\n";
    echo "   \$service = \$parser->getService('doc/simple.api');\n";
}
