<?php

declare(strict_types=1);

namespace Tivins\Solid\ISP;

use PhpParser\Node;
use PhpParser\Node\Stmt;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor\NameResolver;
use PhpParser\NodeVisitorAbstract;
use PhpParser\ParserFactory;
use ReflectionClass;
use ReflectionMethod;

/**
 * Detects incomplete interface implementations: methods that implement an interface
 * but contain a TODO (or similar) and a trivial constant return (e.g. always false,
 * true, null, empty array).
 *
 * Such stubs do not fulfil a meaningful contract; clients depending on the interface
 * cannot rely on the implementation. This is an ISP (and LSP) concern.
 *
 * Incomplete markers: TODO, FIXME, XXX, HACK, or "Implement … method" in comments/code.
 * Trivial returns: return true; return false; return null; return []; return 0; return 1;
 */
class IncompleteImplementationRuleChecker implements IspRuleCheckerInterface
{
    /**
     * Substrings that indicate an incomplete/stub implementation (case-insensitive).
     *
     * @var list<string>
     */
    private const INCOMPLETE_MARKERS = [
        'TODO',
        'FIXME',
        'XXX',
        'HACK',
        'Implement ',  // e.g. "Implement check() method"
    ];

    /** @var array<string, Stmt\ClassLike|null> */
    private array $astCache = [];

    /**
     * @param ReflectionClass<object> $class
     * @param ReflectionClass<object> $interface
     */
    public function check(ReflectionClass $class, ReflectionClass $interface): array
    {
        $violations = [];

        foreach ($interface->getMethods() as $interfaceMethod) {
            if (!$class->hasMethod($interfaceMethod->getName())) {
                continue;
            }

            $classMethod = $class->getMethod($interfaceMethod->getName());

            if ($classMethod->getDeclaringClass()->getName() !== $class->getName()) {
                continue;
            }

            if ($classMethod->isAbstract()) {
                continue;
            }

            if ($this->hasIncompleteMarker($classMethod) && $this->hasTrivialConstantReturn($classMethod)) {
                $violations[] = new IspViolation(
                    className: $class->getName(),
                    interfaceName: $interface->getName(),
                    reason: sprintf(
                        'Method %s() appears to be an incomplete implementation (contains TODO/FIXME and trivial return).',
                        $classMethod->getName(),
                    ),
                );
            }
        }

        return $violations;
    }

    /**
     * Check if the method body (source lines) contains any incomplete marker.
     */
    private function hasIncompleteMarker(ReflectionMethod $method): bool
    {
        $fileName = $method->getDeclaringClass()->getFileName();
        if ($fileName === false) {
            return false;
        }

        $code = file_get_contents($fileName);
        if ($code === false) {
            return false;
        }

        $lines = explode("\n", $code);
        $start = $method->getStartLine() - 1;
        $end = $method->getEndLine();
        if ($start < 0 || $end > count($lines)) {
            return false;
        }

        $methodBody = implode("\n", array_slice($lines, $start, $end - $start));

        foreach (self::INCOMPLETE_MARKERS as $marker) {
            if (stripos($methodBody, $marker) !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * Detect if the method body is only a trivial constant return (true, false, null, [], 0, 1).
     */
    private function hasTrivialConstantReturn(ReflectionMethod $method): bool
    {
        $stmts = $this->getMethodAstStatements($method);
        if ($stmts === null) {
            return false;
        }

        $stmts = array_values(array_filter(
            $stmts,
            fn(Node $s) => !($s instanceof Stmt\Nop),
        ));

        if (count($stmts) !== 1) {
            return false;
        }

        $stmt = $stmts[0];
        if (!$stmt instanceof Stmt\Return_) {
            return false;
        }

        if ($stmt->expr === null) {
            return true; // return;
        }

        $expr = $stmt->expr;

        if ($expr instanceof Node\Expr\ConstFetch) {
            $name = strtolower($expr->name->toString());
            return in_array($name, ['true', 'false', 'null'], true);
        }

        if ($expr instanceof Node\Expr\Array_ && $expr->items === []) {
            return true; // return [];
        }

        if ($expr instanceof Node\Scalar\LNumber) {
            $val = $expr->value;
            return $val === 0 || $val === 1;
        }

        return false;
    }

    /**
     * @return Stmt[]|null
     */
    private function getMethodAstStatements(ReflectionMethod $method): ?array
    {
        $declaringClass = $method->getDeclaringClass();
        $fileName = $declaringClass->getFileName();
        if ($fileName === false) {
            return null;
        }

        $classNode = $this->getClassAstNode($fileName, $declaringClass->getName());
        if ($classNode === null) {
            return null;
        }

        foreach ($classNode->stmts as $stmt) {
            if ($stmt instanceof Stmt\ClassMethod && $stmt->name->toString() === $method->getName()) {
                return $stmt->stmts ?? [];
            }
        }

        return null;
    }

    private function getClassAstNode(string $fileName, string $className): ?Stmt\ClassLike
    {
        $cacheKey = $fileName . '::' . $className;
        if (array_key_exists($cacheKey, $this->astCache)) {
            return $this->astCache[$cacheKey];
        }

        $code = file_get_contents($fileName);
        if ($code === false) {
            $this->astCache[$cacheKey] = null;
            return null;
        }

        $parser = (new ParserFactory())->createForNewestSupportedVersion();
        $stmts = $parser->parse($code);
        if ($stmts === null) {
            $this->astCache[$cacheKey] = null;
            return null;
        }

        $traverser = new NodeTraverser();
        $traverser->addVisitor(new NameResolver());

        $collector = new class ($className) extends NodeVisitorAbstract {
            public ?Stmt\ClassLike $found = null;

            public function __construct(private readonly string $targetClass)
            {
            }

            public function enterNode(Node $node): null
            {
                if (($node instanceof Stmt\Class_ || $node instanceof Stmt\Trait_)
                    && $node->namespacedName !== null
                    && $node->namespacedName->toString() === $this->targetClass
                ) {
                    $this->found = $node;
                }
                return null;
            }
        };

        $traverser->addVisitor($collector);

        try {
            $traverser->traverse($stmts);
        } catch (\LogicException $e) {
            $this->astCache[$cacheKey] = null;
            return null;
        }

        $this->astCache[$cacheKey] = $collector->found;
        return $collector->found;
    }
}
