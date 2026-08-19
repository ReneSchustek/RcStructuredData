<?php

declare(strict_types=1);

namespace Ruhrcoder\RcStructuredData\Controller;

use Ruhrcoder\RcStructuredData\Schema\Diagnostics\DiagnoseResult;
use Ruhrcoder\RcStructuredData\Schema\Diagnostics\SchemaDiagnoser;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Admin-API-Endpunkt für die Schema-Diagnose einer Kategorie.
 *
 * Baut den Graphen der Kategorie nach (via SchemaDiagnoser) und liefert Knotenzahl + Lücken je Knoten
 * — Datengrundlage für die sichtbare Diagnose-Karte in der Kategorie-Detailseite. Reine Delegation an
 * den Diagnoser (SoC).
 *
 * **Beide Routen verlangen ein Recht.** Der Bereich `api` erzwingt nur eine gültige Anmeldung; ohne
 * `_acl` käme jeder Zugang an die Diagnose, auch eine Integration, die für einen einzigen eng
 * geschnittenen Zweck angelegt wurde. Verlangt wird das Leserecht auf die Entität, über die
 * Auskunft gegeben wird — kein eigenes Recht, solange ein Recht des Kerns die Frage beantwortet.
 */
#[Route(defaults: ['_routeScope' => ['api']])]
final class SchemaDiagnoseController
{
    public function __construct(
        private readonly SchemaDiagnoser $diagnoser,
    ) {
    }

    #[Route(
        path: '/api/_action/rc-schema/diagnose/category/{categoryId}',
        name: 'api.action.rc-schema.diagnose.category',
        defaults: ['_acl' => ['category:read']],
        methods: ['GET'],
    )]
    public function diagnoseCategory(string $categoryId): JsonResponse
    {
        return $this->toResponse($this->diagnoser->diagnose($categoryId));
    }

    #[Route(
        path: '/api/_action/rc-schema/diagnose/landing-page/{landingPageId}',
        name: 'api.action.rc-schema.diagnose.landing-page',
        defaults: ['_acl' => ['landing_page:read']],
        methods: ['GET'],
    )]
    public function diagnoseLandingPage(string $landingPageId): JsonResponse
    {
        return $this->toResponse($this->diagnoser->diagnoseLandingPage($landingPageId));
    }

    private function toResponse(DiagnoseResult $result): JsonResponse
    {
        return new JsonResponse([
            'found' => $result->categoryFound,
            'name' => $result->categoryName,
            'hasGraph' => $result->graph !== null,
            'nodeCount' => $result->nodeCount(),
            'findings' => $result->findings,
            'warnings' => $result->warnings,
        ]);
    }
}
