<?php

declare(strict_types=1);

namespace Ruhrcoder\RcStructuredData\Tests\Unit\Controller;

use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;
use Ruhrcoder\RcStructuredData\Controller\SchemaDiagnoseController;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Nagelt fest, dass beide Diagnose-Routen ein Recht verlangen.
 *
 * Der Bereich `api` erzwingt nur eine gültige Anmeldung. Ohne `_acl` käme jeder Zugang an die
 * Diagnose — auch eine Integration, die für einen einzigen, eng geschnittenen Zweck angelegt
 * wurde. Der Fehler wäre still: Die Route antwortet ordnungsgemäß, nur eben jedem.
 *
 * Der Test liest die Angaben an der Methode selbst. Damit fällt auch auf, wenn jemand später
 * eine dritte Route ergänzt und das Recht vergisst.
 */
final class SchemaDiagnoseControllerAclTest extends TestCase
{
    /**
     * Erwartetes Recht je Methode. Verlangt wird das Leserecht auf die Entität, über die
     * Auskunft gegeben wird — kein eigenes Recht, solange ein Recht des Kerns die Frage
     * beantwortet.
     */
    private const EXPECTED_ACL = [
        'diagnoseCategory' => 'category:read',
        'diagnoseLandingPage' => 'landing_page:read',
    ];

    public function testEveryRouteRequiresItsAcl(): void
    {
        foreach (self::EXPECTED_ACL as $method => $expected) {
            $route = $this->routeOf($method);

            self::assertSame(
                [$expected],
                $route->getDefaults()['_acl'] ?? null,
                \sprintf('%s muss %s verlangen.', $method, $expected),
            );
        }
    }

    /**
     * Die Gegenprobe gegen das Vergessen: **jede** öffentliche Methode mit einer Route trägt
     * ein Recht — nicht nur die beiden, die dieser Test namentlich kennt.
     */
    public function testNoRouteIsLeftWithoutAnAcl(): void
    {
        $reflection = new ReflectionClass(SchemaDiagnoseController::class);

        foreach ($reflection->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            $attributes = $method->getAttributes(Route::class);
            if ($attributes === []) {
                continue;
            }

            $route = $attributes[0]->newInstance();

            self::assertArrayHasKey(
                '_acl',
                $route->getDefaults(),
                \sprintf('%s hat eine Route, aber kein Recht.', $method->getName()),
            );
        }
    }

    private function routeOf(string $method): Route
    {
        $attributes = (new ReflectionMethod(SchemaDiagnoseController::class, $method))
            ->getAttributes(Route::class);

        self::assertNotEmpty($attributes, \sprintf('%s hat keine Route.', $method));

        return $attributes[0]->newInstance();
    }
}
