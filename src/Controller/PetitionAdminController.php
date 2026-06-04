<?php

declare(strict_types=1);

namespace App\Controller;

use App\Petition\PetitionAdminAuth;
use App\Petition\PetitionRepository;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Waaseyaa\SSR\SsrServiceProvider;

/**
 * Authenticated admin for the petition system: list campaigns with their
 * counts, create / deactivate a campaign, and export verified signatures to
 * CSV so they can be handed to the administration. Every action is gated by
 * PetitionAdminAuth (fails closed). The CSV is the audited artefact and is the
 * only place email addresses leave the database.
 */
final class PetitionAdminController
{
    public function __construct(
        private readonly PetitionRepository $petitions,
        private readonly PetitionAdminAuth $auth,
    ) {}

    public function index(Request $request): Response
    {
        if ($denied = $this->auth->guard($request)) {
            return $denied;
        }

        return $this->render('petition/admin.html.twig', [
            'campaigns' => $this->petitions->listCampaigns(),
        ]);
    }

    public function create(Request $request): Response
    {
        if ($denied = $this->auth->guard($request)) {
            return $denied;
        }

        $slug = $this->slugify((string) $request->request->get('slug', ''));
        $title = trim((string) $request->request->get('title', ''));
        $ask = trim((string) $request->request->get('the_ask', ''));
        $recipient = trim((string) $request->request->get('recipient', ''));

        if ($slug !== '' && $title !== '' && $ask !== '' && $recipient !== '') {
            $this->petitions->ensureCampaign($slug, $title, $ask, $recipient);
        }

        return $this->redirect('/admin/petitions');
    }

    public function setActive(Request $request, string $slug): Response
    {
        if ($denied = $this->auth->guard($request)) {
            return $denied;
        }

        $active = (string) $request->request->get('active', '1') === '1';
        $this->petitions->setCampaignActive($slug, $active);

        return $this->redirect('/admin/petitions');
    }

    /** GET /admin/petitions/{slug}/export.csv — verified, non-deleted rows. */
    public function export(Request $request, string $slug): Response
    {
        if ($denied = $this->auth->guard($request)) {
            return $denied;
        }

        $campaign = $this->petitions->findCampaign($slug);
        if ($campaign === null) {
            return new Response('Unknown campaign.', 404, ['Content-Type' => 'text/plain; charset=UTF-8']);
        }

        $rows = $this->petitions->exportRows((int) $campaign['id']);

        $out = fopen('php://temp', 'r+');
        fputcsv($out, ['name', 'email', 'member_or_supporter', 'comment', 'show_name_publicly', 'signed_at_utc']);
        foreach ($rows as $r) {
            fputcsv($out, [
                (string) $r['name'],
                (string) $r['email'],
                (string) $r['member_flag'],
                (string) ($r['comment'] ?? ''),
                ((int) $r['show_name_publicly'] === 1) ? 'yes' : 'no',
                (string) $r['created_at'],
            ]);
        }
        rewind($out);
        $csv = (string) stream_get_contents($out);
        fclose($out);

        $filename = 'petition-' . $slug . '-' . gmdate('Ymd') . '.csv';

        return new Response($csv, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            // Belt and braces: never let a CDN cache a PII export.
            'Cache-Control' => 'no-store, private',
        ]);
    }

    // ---- helpers ---------------------------------------------------------

    private function slugify(string $value): string
    {
        $value = strtolower(trim($value));
        $value = preg_replace('/[^a-z0-9]+/', '-', $value) ?? '';

        return trim($value, '-');
    }

    private function redirect(string $to): Response
    {
        return new Response('', 303, ['Location' => $to]);
    }

    /** @param array<string, mixed> $context */
    private function render(string $template, array $context): Response
    {
        $twig = SsrServiceProvider::getTwigEnvironment();
        if ($twig === null) {
            return new Response('Page unavailable: Twig is not initialised.', 500);
        }

        return new Response(
            $twig->render($template, $context),
            200,
            ['Content-Type' => 'text/html; charset=UTF-8', 'Cache-Control' => 'no-store, private'],
        );
    }
}
