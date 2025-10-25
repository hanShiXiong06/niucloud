<?php
/**
 * 简单转账服务测试
 */

// 设置错误报告
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "=== 简单转账测试 ===\n\n";

// 尝试加载框架
try {
    // 改为正确的框架入口
    require_once __DIR__ . '/../../app.php';
    
    echo "1. 框架加载成功\n";
    
    // 测试CoreTransferService是否可以实例化
    echo "\n2. 测试CoreTransferService实例化...\n";
    try {
        $transferServiceClass = '\\app\\service\\core\\pay\\CoreTransferService';
        if (class_exists($transferServiceClass)) {
            $transferService = new $transferServiceClass();
            echo "  ✓ CoreTransferService实例化成功\n";
            
            // 测试create方法是否存在
            echo "\n3. 测试create方法...\n";
            if (method_exists($transferService, 'create')) {
                echo "  ✓ create方法存在\n";
                
                // 尝试创建转账记录
                echo "\n4. 尝试创建测试转账记录...\n";
                try {
                    $transferNo = $transferService->create(
                        1,                    // site_id  
                        'recycle_order',      // main_type
                        999999,               // main_id (测试ID)
                        100.50,               // money
                        'recycle_payment',    // trade_type
                        '测试转账记录 - ' . date('Y-m-d H:i:s')
                    );
                    
                    if ($transferNo) {
                        echo "  ✓ 转账记录创建成功\n";
                        echo "  转账单号: {$transferNo}\n";
                        
                        // 清理测试数据
                        echo "\n5. 清理测试数据...\n";
                        try {
                            $transfer = $transferService->findTransferByTransferNo(1, $transferNo);
                            if ($transfer && !$transfer->isEmpty()) {
                                $transfer->delete();
                                echo "  ✓ 测试数据清理完成\n";
                            }
                        } catch (Exception $e) {
                            echo "  ! 清理警告: " . $e->getMessage() . "\n";
                        }
                        
                    } else {
                        echo "  ✗ 转账记录创建失败：返回空值\n";
                    }
                    
                } catch (Exception $e) {
                    echo "  ✗ 转账记录创建失败: " . $e->getMessage() . "\n";
                    echo "  错误详情: " . $e->getFile() . ":" . $e->getLine() . "\n";
                }
                
            } else {
                echo "  ✗ create方法不存在\n";
            }
            
        } else {
            echo "  ✗ CoreTransferService类不存在\n";
        }
        
    } catch (Exception $e) {
        echo "  ✗ CoreTransferService实例化失败: " . $e->getMessage() . "\n";
    }
    
    echo "\n=== 测试完成 ===\n";
    
} catch (Exception $e) {
    echo "框架加载失败: " . $e->getMessage() . "\n";
    echo "错误文件: " . $e->getFile() . ":" . $e->getLine() . "\n";
    
    // 尝试其他加载方式
    echo "\n尝试其他加载方式...\n";
    
    $possiblePaths = [
        __DIR__ . '/../../bootstrap.php',
        __DIR__ . '/../../index.php', 
        __DIR__ . '/../../think',
        __DIR__ . '/../../../start.php'
    ];
    
    foreach ($possiblePaths as $path) {
        if (file_exists($path)) {
            echo "找到可能的入口文件: {$path}\n";
        }
    }
} 