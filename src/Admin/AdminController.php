<?php

declare(strict_types=1);

namespace App\Admin;

use Anokii\Access\AdminRoles;
use Anokii\Controller\AnokiiAdminController;
use Anokii\Dashboard\DashboardGate;
use App\Controller\AnalyticsDashboardController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Waaseyaa\Entity\EntityTypeManager;

/**
 * Framework-auth gate for oiatc's admin surfaces, on the shared Anokii package
 * gate (Anokii\Dashboard\DashboardGate). It wraps the existing admin renderers,
 * the package's lean /admin/anokii (graph counts + no-PII chat log) and oiatc's
 * /admin/analytics dashboard, behind requirePermission(), so both require an
 * admin login instead of being public.
 *
 * Registered at a higher route priority than the package's own ungated
 * /admin/anokii (which a shared-graph install mounts at priority 100), so this
 * gated route wins deterministically regardless of provider registration order.
 * Anonymous -> /admin/login; signed-in without the permission -> 403; admin ->
 * the dashboard.
 */
final class AdminController extends DashboardGate
{
    public function __construct(
        ?EntityTypeManager $entityTypeManager,
        private readonly AnokiiAdminController $anokiiAdmin,
        private readonly AnalyticsDashboardController $analyticsDashboard,
    ) {
        parent::__construct($entityTypeManager);
    }

    protected function loginPath(): string
    {
        return '/admin/login';
    }

    /** The package lean admin (graph counts + content-gap log), gated. */
    public function anokii(Request $request): Response
    {
        return $this->requirePermission($request, AdminRoles::DEFAULT_PERMISSION)
            ?? $this->anokiiAdmin->index($request);
    }

    /** The first-party analytics dashboard, gated. */
    public function analytics(Request $request): Response
    {
        return $this->requirePermission($request, AdminRoles::DEFAULT_PERMISSION)
            ?? $this->analyticsDashboard->index($request);
    }
}
