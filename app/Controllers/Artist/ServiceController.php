<?php

declare(strict_types=1);

namespace App\Controllers\Artist;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Exceptions\ValidationException;
use App\Core\Request;
use App\Core\Uploader;
use App\Models\Artist;
use App\Models\AuditLog;
use App\Models\Portfolio;
use App\Models\Service;
use App\Models\Subscription;

/**
 * Service menu and portfolio management.
 *
 * Both are gated by the artist's plan allowance. The check happens here, on
 * the write path, not only in the template — hiding a button is not a limit.
 */
final class ServiceController extends Controller
{
    public function index(Request $request): never
    {
        $artistId = (int) Auth::id();

        $this->view('artist/services', [
            'title'      => 'Services',
            'services'   => Service::forArtist($artistId),
            'categories' => Service::categories(),
            'allowance'  => Subscription::serviceAllowance($artistId),
            'plan'       => Subscription::planFor($artistId),
            'usedSlots'  => Service::countActive($artistId),
            'editing'    => $request->query('edit') !== null
                ? Service::findForArtist((int) $request->query('edit'), $artistId)
                : null,
        ]);
    }

    public function store(Request $request): never
    {
        $artistId = (int) Auth::id();
        $data     = $this->validateService($request, '/artist/services');

        $wantsActive = !empty($data['is_active']);

        // Only *active* services count against the plan, so someone at their
        // limit can still draft a new one and swap which is published.
        if ($wantsActive) {
            $allowance = Subscription::serviceAllowance($artistId);
            if (!$allowance['allowed']) {
                $this->failure((string) $allowance['message'], '/artist/subscription');
            }
        }

        $serviceId = Service::create($artistId, $data);
        AuditLog::record('service.created', 'service', $serviceId);

        $this->success('Service added.', '/artist/services');
    }

    public function update(Request $request): never
    {
        $artistId  = (int) Auth::id();
        $serviceId = $request->intParam('id');

        $existing = Service::findForArtist($serviceId, $artistId);
        if ($existing === null) {
            $this->abort(404, 'We could not find that service.');
        }

        $data = $this->validateService($request, '/artist/services?edit=' . $serviceId);

        // Re-activating a service consumes an allowance slot.
        $activating = !empty($data['is_active']) && (int) $existing['is_active'] === 0;
        if ($activating) {
            $allowance = Subscription::serviceAllowance($artistId);
            if (!$allowance['allowed']) {
                $this->failure((string) $allowance['message'], '/artist/subscription');
            }
        }

        Service::update($serviceId, $artistId, $data);
        AuditLog::record('service.updated', 'service', $serviceId);

        $this->success('Service updated.', '/artist/services');
    }

    public function toggle(Request $request): never
    {
        $artistId  = (int) Auth::id();
        $serviceId = $request->intParam('id');

        $service = Service::findForArtist($serviceId, $artistId);
        if ($service === null) {
            $this->abort(404, 'We could not find that service.');
        }

        $activating = (int) $service['is_active'] === 0;

        if ($activating) {
            $allowance = Subscription::serviceAllowance($artistId);
            if (!$allowance['allowed']) {
                $this->failure((string) $allowance['message'], '/artist/subscription');
            }
        }

        Service::setActive($serviceId, $artistId, $activating);
        AuditLog::record($activating ? 'service.activated' : 'service.deactivated', 'service', $serviceId);

        $this->success($activating ? 'Service is now live.' : 'Service hidden from your profile.', '/artist/services');
    }

    public function destroy(Request $request): never
    {
        $artistId  = (int) Auth::id();
        $serviceId = $request->intParam('id');

        if (!Service::delete($serviceId, $artistId)) {
            $this->abort(404, 'We could not find that service.');
        }

        AuditLog::record('service.deleted', 'service', $serviceId);

        $this->success('Service removed. Past bookings keep their original details.', '/artist/services');
    }

    /** @return array<string, mixed> */
    private function validateService(Request $request, string $redirectTo): array
    {
        $data = $this->validate($request, [
            'name'         => 'required|min:2|max:140',
            'description'  => 'max:2000',
            'category_id'  => 'integer',
            'duration_min' => 'required|integer|gte:5|lte:600',
            'buffer_min'   => 'integer|gte:0|lte:180',
            'price'        => 'required|numeric|gte:0|lte:1000000',
        ], [
            'name'         => 'Service name',
            'duration_min' => 'Duration',
            'buffer_min'   => 'Clean-up time',
            'price'        => 'Price',
        ], $redirectTo);

        $data['is_active']   = $request->bool('is_active');
        $data['sort_order']  = $request->int('sort_order');
        $data['category_id'] = (int) ($data['category_id'] ?? 0);

        return $data;
    }

    // -- Portfolio ----------------------------------------------------------

    public function portfolio(Request $request): never
    {
        $artistId = (int) Auth::id();

        // Only link to the public pages once the profile is actually reachable
        // — an unapproved artist would land on a 404 and think it was broken.
        $profile   = Artist::findByUserId($artistId, true);
        $slug      = $profile['slug'] ?? null;

        $this->view('artist/portfolio', [
            'title'     => 'Portfolio',
            'items'     => Portfolio::forArtist($artistId),
            'services'  => Service::forArtist($artistId),
            'allowance' => Subscription::portfolioAllowance($artistId),
            'plan'      => Subscription::planFor($artistId),
            'usedSlots' => Service::countActive($artistId),
            'publicUrl' => $slug === null ? null : url('/artists/' . $slug . '/portfolio'),
            'publicProfileUrl' => $slug === null ? null : url('/artists/' . $slug),
        ]);
    }

    public function storePortfolio(Request $request): never
    {
        $artistId  = (int) Auth::id();
        $allowance = Subscription::portfolioAllowance($artistId);

        if (!$allowance['allowed']) {
            $this->failure((string) $allowance['message'], '/artist/subscription');
        }

        $file = $request->file('image');
        if ($file === null) {
            $this->failValidation($request, ['image' => 'Choose a photo to upload.'], '/artist/portfolio');
        }

        try {
            $path = Uploader::image($file, 'portfolio', 'image');
        } catch (ValidationException $e) {
            $this->failValidation($request, $e->errors(), '/artist/portfolio');
        }

        $caption   = mb_substr($request->string('caption'), 0, 180);
        $serviceId = $request->int('service_id');

        // Only link to a service the artist actually owns.
        if ($serviceId > 0 && Service::findForArtist($serviceId, $artistId) === null) {
            $serviceId = 0;
        }

        $itemId = Portfolio::create($artistId, $path, $caption ?: null, $serviceId ?: null);
        AuditLog::record('portfolio.added', 'portfolio', $itemId);

        $this->success('Photo added to your portfolio.', '/artist/portfolio');
    }

    public function destroyPortfolio(Request $request): never
    {
        $artistId = (int) Auth::id();
        $itemId   = $request->intParam('id');

        $item = Portfolio::findForArtist($itemId, $artistId);
        if ($item === null) {
            $this->abort(404, 'We could not find that photo.');
        }

        Portfolio::delete($itemId, $artistId);
        Uploader::delete($item['image_path'] ?? null);
        AuditLog::record('portfolio.deleted', 'portfolio', $itemId);

        $this->success('Photo removed.', '/artist/portfolio');
    }
}
