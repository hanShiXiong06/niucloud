<?php

namespace addon\hsx_clipboard_upload;

use think\facade\Log;

/**
 * 剪贴板图片上传插件
 */
class Addon
{
    private const INJECT_START = '// hsx_clipboard_upload:auto-import:start';
    private const INJECT_END = '// hsx_clipboard_upload:auto-import:end';
    private const IMPORT_LINES = [
        "import '@/addon/hsx_clipboard_upload/static/global-clipboard.js'",
        "import '@/addon/hsx_clipboard_upload/static/global-drag-upload.js'",
    ];

    /**
     * 插件安装执行
     */
    public function install()
    {
        $this->injectAdminMainImports();
        return true;
    }

    /**
     * 插件卸载执行
     */
    public function uninstall()
    {
        $this->removeAdminMainImports();
        return true;
    }

    /**
     * 插件升级执行
     */
    public function upgrade()
    {
        $this->injectAdminMainImports();
        return true;
    }

    /**
     * 后台前端入口文件路径：项目根目录/admin/src/main.ts
     */
    private function getAdminMainPath(): string
    {
        return dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . 'admin' . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . 'main.ts';
    }

    /**
     * 安装/升级时自动向 admin/src/main.ts 注入全局剪贴板与拖拽上传脚本。
     */
    private function injectAdminMainImports(): void
    {
        $path = $this->getAdminMainPath();
        if (!is_file($path) || !is_readable($path) || !is_writable($path)) {
            Log::warning('hsx_clipboard_upload 自动注入失败：main.ts 不存在或不可写', ['path' => $path]);
            return;
        }

        $content = file_get_contents($path);
        if ($content === false) {
            Log::warning('hsx_clipboard_upload 自动注入失败：main.ts 读取失败', ['path' => $path]);
            return;
        }

        $content = $this->removeImportBlock($content);
        $block = self::INJECT_START . PHP_EOL
            . implode(PHP_EOL, self::IMPORT_LINES) . PHP_EOL
            . self::INJECT_END . PHP_EOL;

        if (preg_match_all('/^import\s.+$/m', $content, $matches, PREG_OFFSET_CAPTURE) && !empty($matches[0])) {
            $last = end($matches[0]);
            $insertAt = $last[1] + strlen($last[0]);
            $content = substr($content, 0, $insertAt) . PHP_EOL . $block . substr($content, $insertAt);
        } else {
            $content = $block . $content;
        }

        file_put_contents($path, $content);
    }

    /**
     * 卸载时删除自动注入块，同时兼容删除早期手动添加的裸 import 行。
     */
    private function removeAdminMainImports(): void
    {
        $path = $this->getAdminMainPath();
        if (!is_file($path) || !is_readable($path) || !is_writable($path)) {
            Log::warning('hsx_clipboard_upload 自动移除失败：main.ts 不存在或不可写', ['path' => $path]);
            return;
        }

        $content = file_get_contents($path);
        if ($content === false) {
            Log::warning('hsx_clipboard_upload 自动移除失败：main.ts 读取失败', ['path' => $path]);
            return;
        }

        file_put_contents($path, $this->removeImportBlock($content));
    }

    private function removeImportBlock(string $content): string
    {
        $start = preg_quote(self::INJECT_START, '/');
        $end = preg_quote(self::INJECT_END, '/');
        $content = preg_replace('/\R?' . $start . '\R.*?\R' . $end . '\R?/s', PHP_EOL, $content) ?? $content;

        foreach (self::IMPORT_LINES as $line) {
            $content = preg_replace('/^\s*' . preg_quote($line, '/') . '\s*;?\s*\R?/m', '', $content) ?? $content;
        }

        return preg_replace("/\n{3,}/", PHP_EOL . PHP_EOL, $content) ?? $content;
    }

} 
