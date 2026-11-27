<?php
declare(strict_types=1);

/**
 * Proves a formatting pass changed layout only.
 *
 * Usage: php ast-compare.php <before-dir> <after-dir>
 *
 * Parses every .php file in both trees as PHP 8.0 and compares the AST dumps
 * without positions or comments. Quote style, array() vs [], whitespace and line
 * breaks are attributes, not structure, so they are allowed to differ; any change
 * to a name, literal value, operator, statement or its order is reported.
 * Exit 0 only if both trees have the same .php file set and every AST is identical.
 */

use PhpParser\NodeDumper;
use PhpParser\ParserFactory;
use PhpParser\PhpVersion;

require __DIR__ . '/vendor/autoload.php';

if (PHP_SAPI !== 'cli' || $argc !== 3) {
    fwrite(STDERR, "Usage: php ast-compare.php <before-dir> <after-dir>\n");
    exit(2);
}

$before = realpath($argv[1]);
$after = realpath($argv[2]);
if ($before === false || $after === false || !is_dir($before) || !is_dir($after)) {
    fwrite(STDERR, "Both arguments must be existing directories\n");
    exit(2);
}

function phpFiles(string $root): array
{
    $files = [];
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));
    foreach ($iterator as $file) {
        if (!$file->isFile() || strtolower($file->getExtension()) !== 'php') {
            continue;
        }
        $relative = str_replace('\\', '/', substr($file->getPathname(), strlen($root) + 1));
        if (str_starts_with($relative, 'tools/format/vendor/') || str_contains($relative, '/node_modules/')) {
            continue;
        }
        $files[] = $relative;
    }
    sort($files);
    return $files;
}

$parser = (new ParserFactory())->createForVersion(PhpVersion::fromString('8.0'));
$dumper = new NodeDumper(['dumpComments' => false, 'dumpPositions' => false]);

function dumpAst($parser, NodeDumper $dumper, string $path): string
{
    $code = file_get_contents($path);
    if ($code === false) {
        throw new RuntimeException('Cannot read ' . $path);
    }
    $ast = $parser->parse($code);
    return $dumper->dump($ast ?? []);
}

$beforeFiles = phpFiles($before);
$afterFiles = phpFiles($after);
$failures = [];

foreach (array_diff($beforeFiles, $afterFiles) as $missing) {
    $failures[] = "MISSING after: $missing";
}
foreach (array_diff($afterFiles, $beforeFiles) as $added) {
    $failures[] = "ADDED after: $added";
}

$compared = 0;
foreach (array_intersect($beforeFiles, $afterFiles) as $relative) {
    try {
        $a = dumpAst($parser, $dumper, $before . '/' . $relative);
        $b = dumpAst($parser, $dumper, $after . '/' . $relative);
    } catch (Throwable $error) {
        $failures[] = "PARSE ERROR: $relative: " . $error->getMessage();
        continue;
    }
    $compared++;
    if ($a !== $b) {
        // Point at the first differing dump line to make review quick.
        $la = explode("\n", $a);
        $lb = explode("\n", $b);
        $line = 0;
        while ($line < min(count($la), count($lb)) && $la[$line] === $lb[$line]) {
            $line++;
        }
        $failures[] = "AST CHANGED: $relative (dump line " . ($line + 1) . ")\n  before: " . trim($la[$line] ?? '<end>')
            . "\n  after:  " . trim($lb[$line] ?? '<end>');
    }
}

echo "Compared $compared PHP files.\n";
if ($failures) {
    echo count($failures) . " problem(s):\n" . implode("\n", $failures) . "\n";
    exit(1);
}
echo "OK: every AST is identical; the pass changed formatting only.\n";
exit(0);
