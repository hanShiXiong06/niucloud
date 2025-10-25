<?php

namespace addon\hsx_clipboard_upload;

use think\facade\Log;

/**
 * 剪贴板图片上传插件
 */
class Addon
{
    /**
     * 插件安装执行
     */
    public function install()
    {
        // $this->injectImportToMainTs();
        return true;
    }

    /**
     * 插件卸载执行
     */
    public function uninstall()
    {
        // $this->removeImportFromMainTs();
        return true;
    }

    /**
     * 插件升级执行
     */
    public function upgrade()
    {
        return true;
    }

    // /**
    //  * 向admin的main.ts文件中注入import语句
    //  */
    // private function injectImportToMainTs()
    // {
    //     try {
    //         // admin的main.ts文件路径 - 从public目录回退到上级目录
    //         $mainTsPath = '../../niucloud/admin/src/main.ts';
            
    //         if (!file_exists($mainTsPath)) {
    //             throw new \Exception("main.ts文件不存在: " . realpath('.') . '/../../niucloud/admin/src/main.ts');
    //         }

    //         // 读取文件内容
    //         $content = file_get_contents($mainTsPath);
    //         if ($content === false) {
    //             throw new \Exception("无法读取main.ts文件");
    //         }

    //         // 要添加的import语句
    //         $importStatement = "import '@/addon/hsx_clipboard_upload/static/global-clipboard.js'";
            
    //         // 检查是否已经存在该import语句
    //         if (strpos($content, $importStatement) !== false) {
    //             // 已存在，无需重复添加
    //             Log::info(json_encode([
    //                 'action' => 'clipboard_upload_install',
    //                 'message' => 'import语句已存在，跳过添加',
    //                 'file' => realpath($mainTsPath)
    //             ]));
    //             return;
    //         }

    //         // 将内容按行分割
    //         $lines = explode("\n", $content);
    //         $insertPosition = -1;
            
    //         // 查找最后一个import语句的位置
    //         for ($i = 0; $i < count($lines); $i++) {
    //             $trimmedLine = trim($lines[$i]);
    //             if (preg_match('/^import\s+/', $trimmedLine)) {
    //                 $insertPosition = $i;
    //             }
    //         }
            
    //         if ($insertPosition !== -1) {
    //             // 在最后一个import语句之后插入
    //             array_splice($lines, $insertPosition + 1, 0, $importStatement);
    //             $newContent = implode("\n", $lines);
    //         } else {
    //             // 如果没有找到import语句，在文件开头插入
    //             $newContent = $importStatement . "\n" . $content;
    //         }

    //         // 写入文件
    //         if (file_put_contents($mainTsPath, $newContent) === false) {
    //             throw new \Exception("无法写入main.ts文件");
    //         }

    //         // 记录日志
    //         Log::info(json_encode([
    //             'action' => 'clipboard_upload_install',
    //             'message' => '成功向main.ts注入剪贴板上传功能',
    //             'file' => realpath($mainTsPath),
    //             'insert_position' => $insertPosition + 1
    //         ]));

    //     } catch (\Exception $e) {
    //         // 记录错误日志
    //         Log::info(json_encode([
    //             'action' => 'clipboard_upload_install_error',
    //             'message' => '注入main.ts失败: ' . $e->getMessage(),
    //             'current_dir' => realpath('.'),
    //             'expected_file' => realpath('.') . '/../../niucloud/admin/src/main.ts',
    //             'file' => $mainTsPath ?? 'unknown'
    //         ]));
            
    //         // 安装失败时不应该阻止插件安装，只记录日志
    //     }
    // }

    // /**
    //  * 从admin的main.ts文件中移除import语句
    //  */
    // private function removeImportFromMainTs()
    // {
    //     try {
    //         // admin的main.ts文件路径 - 从public目录回退到上级目录
    //         $mainTsPath = '../../niucloud/admin/src/main.ts';
            
    //         if (!file_exists($mainTsPath)) {
    //             throw new \Exception("main.ts文件不存在: " . realpath('.') . '/../../niucloud/admin/src/main.ts');
    //         }

    //         // 读取文件内容
    //         $content = file_get_contents($mainTsPath);
    //         if ($content === false) {
    //             throw new \Exception("无法读取main.ts文件");
    //         }

    //         // 要移除的import语句
    //         $importStatement = "import '@/addon/hsx_clipboard_upload/static/global-clipboard.js'";
            
    //         // 检查是否存在该import语句
    //         if (strpos($content, $importStatement) === false) {
    //             // 不存在，无需移除
    //             Log::info(json_encode([
    //                 'action' => 'clipboard_upload_uninstall',
    //                 'message' => 'import语句不存在，跳过移除',
    //                 'file' => realpath($mainTsPath)
    //             ]));
    //             return;
    //         }

    //         // 将内容按行分割
    //         $lines = explode("\n", $content);
    //         $removedCount = 0;
            
    //         // 查找并移除包含import语句的行
    //         foreach ($lines as $index => $line) {
    //             if (trim($line) === $importStatement) {
    //                 unset($lines[$index]);
    //                 $removedCount++;
    //                 break; // 只移除第一个匹配的行
    //             }
    //         }
            
    //         if ($removedCount > 0) {
    //             // 重新索引数组并合并
    //             $lines = array_values($lines);
    //             $newContent = implode("\n", $lines);
                
    //             // 写入文件
    //             if (file_put_contents($mainTsPath, $newContent) === false) {
    //                 throw new \Exception("无法写入main.ts文件");
    //             }
    //         }

    //         // 记录日志
    //         Log::info(json_encode([
    //             'action' => 'clipboard_upload_uninstall',
    //             'message' => '成功从main.ts移除剪贴板上传功能',
    //             'file' => realpath($mainTsPath),
    //             'removed_count' => $removedCount
    //         ]));

    //     } catch (\Exception $e) {
    //         // 记录错误日志
    //         Log::info(json_encode([
    //             'action' => 'clipboard_upload_uninstall_error',
    //             'message' => '移除main.ts失败: ' . $e->getMessage(),
    //             'current_dir' => realpath('.'),
    //             'expected_file' => realpath('.') . '/../../niucloud/admin/src/main.ts',
    //             'file' => $mainTsPath ?? 'unknown'
    //         ]));
            
    //         // 卸载失败时不应该阻止插件卸载，只记录日志
    //     }
    // }
} 