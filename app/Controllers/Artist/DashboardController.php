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
use App\Models\Availability;
use App\Models\Booking;
use App\Models\Portfolio;
use App\Models\Review;
use App\Models\Service;
use App\Models\Subscription;

final class DashboardController extends Controller
{
    public function index(Request $request): never
    {
        $artistId = (int) Auth::id();
        $user     = Auth::user();

        $this->view('artist/overview', [
            'title'      => 'Dashboard',
            'stats'      => Booking::artistStats($artistId),
            'today'      => Booking::todayFor($artistId),
            'pending'    => Booking::forUser($artistId, 'artist', 'pending', 5),
            'upcoming'   => Booking::forUser($artistId, 'artist', 'upcoming', 5),
            'profile'    => Artist::findByUserId($artistId),
            'plan'       => Subscription::planFor($artistId),
            'usedSlots'  => Service::countActive($artistId),
            'isApproved' => !empty($user['approved_at']),
            'checklist'  => $this->onboardingChecklist($artistId, $user),
        ]);
    }

    /**
     * Setup progress shown until an artist is fully live — it is the difference
     * between "signed up" and "actually bookable".
     *
     * @return array<int, array{label:string, done:bool, url:string}>
     */
    private function onboardingChecklist(int $artistId, array $user): array
    {
        $profile = Artist::findByUserId($artistId);

        return [
            [
                'label' => 'Add a profile photo and bio',
                'done'  => !empty($user['avatar_path']) && !empty($profile['bio']),
                'url'   => url('/artist/profile'),
            ],
            [
                'label' => 'List at least one service',
                'done'  => Service::countActive($artistId) > 0,
                'url'   => url('/artist/services'),
            ],
            [
                'label' => 'Set your working hours',
                'done'  => Availability::rulesFor($artistId) !== [],
                'url'   => url('/artist/schedule'),
            ],
            [
                'label' => 'Upload portfolio photos',
                'done'  => Portfolio::count($artistId) > 0,
                'url'   => url('/artist/portfolio'),
            ],
            [
                'label' => 'Approved by an admin',
                'done'  => !empty($user['approved_at']),
                'url'   => url('/artist'),
            ],
        ];
    }

    // -- Bookings -----------------------------------------------------------

    public function bookings(Request $request): never
    {
        $artistId = (int) Auth::id();

        $scope = (string) $request->query('tab', 'upcoming');
        if (!in_array($scope, ['upcoming', 'pending', 'past', 'cancelled'], true)) {
            $scope = 'upcoming';
        }

        $stats = Booking::artistStats($artistId);

        $this->view('artist/bookings', [
            'title'    => 'Bookings',
            'bookings' => Booking::forUser($artistId, 'artist', $scope, 100),
            'scope'    => $scope,
            'counts'   => [
                'upcoming'  => (int) ($stats['upcoming'] ?? 0),
                'pending'   => (int) ($stats['pending'] ?? 0),
                'past'      => (int) ($stats['completed'] ?? 0),
                'cancelled' => (int) ($stats['cancelled'] ?? 0),
            ],
            'plan'      => Subscription::planFor($artistId),
            'usedSlots' => Service::countActive($artistId),
        ]);
    }

    public function clients(Request $request): never
    {
        $artistId = (int) Auth::id();

        $this->view('artist/clients', [
            'title'     => 'Clients',
            'clients'   => Booking::clientsFor($artistId),
            'plan'      => Subscription::planFor($artistId),
            'usedSlots' => Service::countActive($artistId),
        ]);
    }

    /**
     * One client's record with this artist: who they are, how often they come
     * back, and every appointment they have had.
     *
     * The summary query is scoped to the signed-in artist, so an id belonging
     * to someone who has never booked them simply 404s.
     */
    public function client(Request $request): never
    {
        $artistId   = (int) Auth::id();
        $customerId = $request->intParam('id');

        $client = $this->abortIfMissing(
            Booking::clientSummaryFor($artistId, $customerId),
            'client'
        );

        $this->view('artist/client-detail', [
            'title'     => $client['full_name'],
            'client'    => $client,
            'history'   => Booking::historyForClient($artistId, $customerId),
            'favourites'=> Booking::favouriteServicesForClient($artistId, $customerId),
            'reviews'   => Review::byCustomerForArtist($customerId, $artistId),
            'plan'      => Subscription::planFor($artistId),
            'usedSlots' => Service::countActive($artistId),
        ]);
    }

    // -- Analytics (paid plans) --------------------------------------------

    public function analytics(Request $request): never
    {
        $artistId = (int) Auth::id();
        $plan     = Subscription::planFor($artistId);

        if ((int) ($plan['has_analytics'] ?? 0) !== 1) {
            $this->view('artist/analytics-locked', [
                'title'     => 'Analytics',
                'plan'      => $plan,
                'usedSlots' => Service::countActive($artistId),
            ]);
        }

        $this->view('artist/analytics', [
            'title'       => 'Analytics',
            'stats'       => Booking::artistStats($artistId),
            'trend'       => Booking::monthlyTrend($artistId, 6),
            'topServices' => Booking::topServices($artistId, 6),
            'distribution'=> Review::distribution($artistId),
            'profile'     => Artist::findByUserId($artistId),
            'plan'        => $plan,
            'usedSlots'   => Service::countActive($artistId),
        ]);
    }

    // -- Reviews ------------------------------------------------------------

    public function reviews(Request $request): never
    {
        $artistId = (int) Auth::id();

        $this->view('artist/reviews', [
            'title'        => 'Reviews',
            'reviews'      => Review::forArtist($artistId, 50),
            'distribution' => Review::distribution($artistId),
            'profile'      => Artist::findByUserId($artistId),
            'plan'         => Subscription::planFor($artistId),
            'usedSlots'    => Service::countActive($artistId),
        ]);
    }

    public function replyToReview(Request $request): never
    {
        $reviewId = $request->intParam('id');

        $data = $this->validate($request, [
            'reply' => 'required|min:2|max:800',
        ], ['reply' => 'Your reply'], '/artist/reviews');

        if (!Review::reply($reviewId, (int) Auth::id(), (string) $data['reply'])) {
            $this->failure('We could not post that reply.', '/artist/reviews');
        }

        AuditLog::record('review.replied', 'review', $reviewId);

        $this->success('Your reply has been posted.', '/artist/reviews');
    }

    // -- Public profile -----------------------------------------------------

    public function profile(Request $request): never
    {
        $artistId = (int) Auth::id();

        $this->view('artist/profile', [
            'title'     => 'Public profile',
            'profile'   => Artist::findByUserId($artistId),
            'user'      => Auth::user(),
            'plan'      => Subscription::planFor($artistId),
            'usedSlots' => Service::countActive($artistId),
        ]);
    }

    public function updateProfile(Request $request): never
    {
        $artistId = (int) Auth::id();

        $data = $this->validate($request, [
            'display_name'     => 'required|min:2|max:120',
            'headline'         => 'max:160',
            'bio'              => 'max:4000',
            'city'             => 'required|max:90',
            'area'             => 'max:120',
            'studio_address'   => 'max:255',
            'years_experience' => 'integer|gte:0|lte:70',
            'instagram'        => 'max:80|alpha_dash',
            'website'          => 'max:190',
            'cancellation_policy' => 'max:500',
        ], [
            'display_name'     => 'Display name',
            'years_experience' => 'Years of experience',
            'instagram'        => 'Instagram handle',
        ], '/artist/profile');

        if (!empty($data['website']) && filter_var($data['website'], FILTER_VALIDATE_URL) === false) {
            $this->failValidation($request, ['website' => 'Enter a full link, including https://'], '/artist/profile');
        }

        $fields = [
            'display_name'     => (string) $data['display_name'],
            'headline'         => $data['headline'] ?: null,
            'bio'              => $data['bio'] ?: null,
            'city'             => (string) $data['city'],
            'area'             => $data['area'] ?: null,
            'studio_address'   => $data['studio_address'] ?: null,
            'years_experience' => (int) ($data['years_experience'] ?: 0),
            'instagram'        => $data['instagram'] ? ltrim((string) $data['instagram'], '@') : null,
            'website'          => $data['website'] ?: null,
            'cancellation_policy' => $data['cancellation_policy'] ?: null,
            'accepts_home_service' => $request->bool('accepts_home_service') ? 1 : 0,
        ];

        // Instant booking is a paid-plan feature; ignore the field otherwise so
        // a crafted POST cannot switch it on.
        if (Subscription::hasInstantBook($artistId)) {
            $fields['instant_book'] = $request->bool('instant_book') ? 1 : 0;
        }

        $cover = $request->file('cover');
        if ($cover !== null) {
            try {
                $existing = Artist::findByUserId($artistId);
                $fields['cover_path'] = Uploader::image($cover, 'covers', 'cover');
                Uploader::delete($existing['cover_path'] ?? null);
            } catch (ValidationException $e) {
                $this->failValidation($request, $e->errors(), '/artist/profile');
            }
        }

        Artist::updateProfile($artistId, $fields);
        AuditLog::record('artist.profile_updated', 'artist', $artistId);

        $this->success('Your public profile has been updated.', '/artist/profile');
    }
}
