<?php
declare(strict_types=1);
namespace addon\hsx_lead\app\listener\poster;

use addon\hsx_lead\app\service\cooperation\PosterShareService;

final class LeadPosterData
{
    public function handle($data = null): array
    {
        if (!is_array($data)) return [];
        $scene = array_search($data['type'] ?? '', PosterShareService::TYPES, true);
        if ($scene === false) return [];
        if (($data['param']['mode'] ?? '') === 'preview' && in_array(request()->appType(), ['site', 'admin'], true)) {
            return PosterShareService::preview((int) ($data['site_id'] ?? 0), $scene, (string) ($data['channel'] ?? ''));
        }
        // Ignore ALL param data, including forged member_id, merchant_id, subject_code and URLs.
        return PosterShareService::fromRequest((int) ($data['site_id'] ?? 0))->data($scene, (string) ($data['channel'] ?? ''));
    }
}
