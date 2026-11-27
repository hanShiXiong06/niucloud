<?php
// 独立构建测试：不启动应用，不连接数据库，所有生成文件均位于调用者提供的临时目录。
namespace core\exception {
    class CommonException extends \RuntimeException {}
}
namespace app\service\core\addon {
    class CoreAddonService {
        public static array $addons = [];
        public function getInstallAddonList(): array {
            return array_map(fn($key) => ['key' => $key], self::$addons);
        }
    }
}
namespace {
    require __DIR__ . '/../niucloud/app/service/core/addon/WapTrait.php';
    class DiyCompiler {
        use \app\service\core\addon\WapTrait;
    }
    function getFileMap($path, $arr = []) {
        if (!is_dir($path)) return null;
        foreach (scandir($path) as $name) {
            if ($name === '.' || $name === '..') continue;
            $file = $path . '/' . $name;
            $arr[$file] = $name;
            if (is_dir($file)) $arr = getFileMap($file, $arr);
        }
        return $arr;
    }
    function put(string $file, string $content): void {
        if (!is_dir(dirname($file))) mkdir(dirname($file), 0777, true);
        file_put_contents($file, $content);
    }
    function check(bool $ok, string $message): void {
        if (!$ok) throw new \RuntimeException($message);
    }
    function fixture(string $base, string $name, array $addons): array {
        $root = $base . '/' . $name . '/';
        put($root . 'app/components/diy/text/index.vue', '<template><view /></template>');
        put($root . 'addon/components/diy/group/index.vue', 'PREVIOUS_VALID_FILE');
        \app\service\core\addon\CoreAddonService::$addons = $addons;
        return [$root, $root . 'addon/components/diy/group/index.vue'];
    }
    function expectFailure(DiyCompiler $compiler, string $root, string $file, string $message): void {
        $previous = file_get_contents($file);
        try {
            $compiler->compileDiyComponentsCode($root, '');
            throw new \RuntimeException('预期失败但生成成功：' . $message);
        } catch (\core\exception\CommonException $e) {
            check(str_contains($e->getMessage(), $message), $e->getMessage());
        }
        check(file_get_contents($file) === $previous, '失败时不应覆盖已有文件');
    }

    $base = $argv[1] ?? '';
    check(is_dir($base) && str_starts_with(realpath($base), realpath(sys_get_temp_dir()) . DIRECTORY_SEPARATOR), '仅允许临时测试目录');
    $manifest = file_get_contents(__DIR__ . '/../uni-app/src/addon/phone_shop/components/diy/registration.json');
    $replacements = json_decode($manifest, true)['replaced_directories'];
    $compiler = new DiyCompiler();
    $outputs = [];
    $cases = [];
    foreach ([['shop', 'phone_shop', 'example'], ['phone_shop', 'shop', 'example'], ['phone_shop']] as $index => $addons) {
        [$root, $file] = fixture($base, 'coexist-' . $index, $addons);
        foreach ($replacements as $old => $replacement) {
            put($root . 'addon/phone_shop/components/diy/' . $old . '/index.vue', '<template><view /></template>');
            put($root . 'addon/phone_shop/components/diy/' . $replacement . '/index.vue', '<template><view /></template>');
            put($root . 'addon/shop/components/diy/' . $old . '/index.vue', '<template><view /></template>');
        }
        put($root . 'addon/phone_shop/components/diy/registration.json', $manifest);
        put($root . 'addon/example/components/diy/other-card/index.vue', '<template><view /></template>');
        put($root . 'addon/phone_shop/components/diy/phone-goods-list/index.vue.bak', 'IGNORE_BACKUP');
        put($root . 'app/components/diy/text/index.vue.old', 'IGNORE_BACKUP');
        $compiler->compileDiyComponentsCode($root, ['phone_shop', 'phone_shop']);
        $result = file_get_contents($file);
        foreach ($replacements as $old => $replacement) {
            check(!str_contains($result, "@/addon/phone_shop/components/diy/{$old}/index.vue"), '旧目录仍被注册');
            check(substr_count($result, "@/addon/phone_shop/components/diy/{$replacement}/index.vue") === 1, '新组件必须只注册一次');
            check(is_file($root . 'addon/phone_shop/components/diy/' . $old . '/index.vue'), '不能删除旧文件');
            if (in_array('shop', $addons)) check(str_contains($result, "@/addon/shop/components/diy/{$old}/index.vue"), '不能移除原商城');
        }
        check(!str_contains($result, '.vue.bak') && !str_contains($result, '.vue.old'), '不应扫描备份文件');
        $compiler->compileDiyComponentsCode($root, 'phone_shop');
        check(file_get_contents($file) === $result, '重复编译必须幂等');
        $outputs[] = $file;
        $cases[] = '共存/安装顺序/仅 phone_shop #' . $index;
    }

    [$root, $file] = fixture($base, 'missing-replacement', ['phone_shop']);
    put($root . 'addon/phone_shop/components/diy/registration.json', $manifest);
    put($root . 'addon/phone_shop/components/diy/shop-exchange-goods/index.vue', '<template><view /></template>');
    expectFailure($compiler, $root, $file, 'phone-shop-exchange-goods/index.vue 缺失');
    $cases[] = '替代组件缺失给出准确路径，不覆盖已有文件';

    [$root, $file] = fixture($base, 'unknown-collision', ['first', 'second']);
    foreach (['first', 'second'] as $addon) put($root . 'addon/' . $addon . '/components/diy/same-card/index.vue', '<template><view /></template>');
    expectFailure($compiler, $root, $file, 'DIY组件重名 SameCard');
    $cases[] = '未知插件冲突不能静默覆盖';

    [$root, $file] = fixture($base, 'system-collision', ['example']);
    put($root . 'addon/example/components/diy/text/index.vue', '<template><view /></template>');
    expectFailure($compiler, $root, $file, 'DIY组件重名 Text');
    $cases[] = '系统与插件同名也能检测';

    foreach (['INVALID_JSON', '{"version":1,"replaced_directories":{"../outside":"phone-safe"}}'] as $index => $invalid) {
        [$root, $file] = fixture($base, 'invalid-manifest-' . $index, ['phone_shop']);
        put($root . 'addon/phone_shop/components/diy/registration.json', $invalid);
        expectFailure($compiler, $root, $file, '配置不正确');
        $cases[] = '非法注册配置 #' . $index;
    }
    echo json_encode(['outputs' => $outputs, 'cases' => $cases], JSON_UNESCAPED_UNICODE);
}
