<?php

declare(strict_types=1);

namespace Tivins\Process;

use InvalidArgumentException;
use PhpParser\Node;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor\NameResolver;
use PhpParser\NodeVisitorAbstract;
use PhpParser\ParserFactory;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

/**
 * Scans a directory recursively for PHP files and extracts fully qualified class names.
 *
 * Uses nikic/php-parser to reliably detect class declarations (with full namespace resolution).
 * Each file containing at least one class is loaded via require_once so the classes
 * become available for reflection.
 */
class ClassFinder
{
    /**
     * Scan a directory recursively for PHP files and return all fully qualified class names found.
     *
     * If the target directory (or an ancestor up to 3 levels) contains a vendor/autoload.php,
     * it is included first so that dependencies are available.
     *
     * @return string[] Fully qualified class names (sorted alphabetically)
     * @throws InvalidArgumentException If the directory does not exist or is not readable
     */
    public function findClassesInDirectory(string $directory): array
    {
        $realDir = realpath($directory);
        if ($realDir === false || !is_dir($realDir)) {
            throw new InvalidArgumentException("Directory not found or not readable: $directory");
        }

        $this->includeAutoloaderIfPresent($realDir);

        $parser = (new ParserFactory())->createForNewestSupportedVersion();
        $classes = [];

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($realDir),
        );

        /** @var SplFileInfo $file */
        foreach ($iterator as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }

            $filePath = $file->getRealPath();
            if ($filePath === false) {
                continue;
            }

            $code = file_get_contents($filePath);
            if ($code === false) {
                continue;
            }

            $stmts = $parser->parse($code);
            if ($stmts === null) {
                continue;
            }

            $fileClasses = $this->extractClassNames($stmts);

            if (!empty($fileClasses)) {
                require_once $filePath;
                array_push($classes, ...$fileClasses);
            }
        }

        sort($classes);
        return $classes;
    }

    /**
     * Extract fully qualified class names from a parsed AST.
     *
     * @param Node\Stmt[] $stmts
     * @return string[]
     */
    private function extractClassNames(array $stmts): array
    {
        $traverser = new NodeTraverser();
        $traverser->addVisitor(new NameResolver());

        $collector = new class extends NodeVisitorAbstract {
            /** @var string[] */
            public array $classes = [];

            public function enterNode(Node $node): null
            {
                if ($node instanceof Node\Stmt\Class_ && $node->namespacedName !== null) {
                    $this->classes[] = $node->namespacedName->toString();
                }
                return null;
            }
        };

        $traverser->addVisitor($collector);
        $traverser->traverse($stmts);

        return $collector->classes;
    }

    /**
     * Look for a vendor/autoload.php in the given directory or up to 3 parent levels,
     * and include it if found. This ensures dependencies of the scanned project are loaded.
     */
    private function includeAutoloaderIfPresent(string $directory): void
    {
        $dir = $directory;
        for ($i = 0; $i < 4; $i++) {
            $autoload = $dir . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php';
            if (file_exists($autoload)) {
                require_once $autoload;
                return;
            }
            $parent = dirname($dir);
            if ($parent === $dir) {
                break; // reached filesystem root
            }
            $dir = $parent;
        }
    }
}
