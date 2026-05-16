<?php
/**
 * PrinterLib 自动加载类
 */
namespace addon\recycle\app\printer\PrinterLib;

class Autoloader
{
    /**
     * 注册自动加载
     * @return void
     */
    public static function register()
    {
        spl_autoload_register(function ($class) {
            // 仅处理特定命名空间下的类
            $prefix = 'addon\\recycle\\app\\printer\\PrinterLib\\';
            $len = strlen($prefix);
            
            if (strncmp($prefix, $class, $len) !== 0) {
                return;
            }
            
            // 获取相对类名
            $relative_class = substr($class, $len);
            
            // 将命名空间部分替换为目录分隔符
            $file = __DIR__ . '/' . str_replace('\\', '/', $relative_class) . '.php';
            
            // 如果文件存在，加载它
            if (file_exists($file)) {
                require $file;
            }
        });
    }
}

spl_autoload_register('\Xpyun\Autoloader::loadClassByNamespace');
?>