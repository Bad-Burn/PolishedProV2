<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Config;
use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Models\Artist;
use App\Models\Favorite;
use App\Models\Portfolio;
use App\Models\Review;
use App\Models\Service;
use App\Services\AvailabilityService;

/**
 * Public artist discovery and profile pages.
 */
final class ArtistDirectoryController extends Controller
{
    public function index(Request $request): never
    {
        $filters = [
            'q'          => (string) $request->query('q', ''),
            'category'   => (string) $request->query('category', ''),
            'city'       => (string) $request->query('city', ''),
            'min_rating' => (float) $request->query('min_rating', 0),
            'max_price'  => (float) $request->query('max_price', 0),
            'verified'   => $request->query('verified') === '1',
            'instant'    => $request->query('instant') === '1',
            'sort'       => (string) $request->query('sort', 'recommended'),
            'page'       => (int) $request->query('page', 1),
            'per_page'   => (int) Config::get('app.pagination.per_page', 12),
        ];

        $results = Artist::search($filters);

        $this->view('pages/artists', [
            'title'   => $this->titleFor($filters),
            'metaDescription' => 'Browse verified beauty professionals, compare prices and reviews, and book a real appointment slot.',
            'results' => $results,
            'filters' => $filters,
            'categories' => Service::categories(),
            'cities'     => Artist::cities(),
            'favoriteIds' => Auth::isCustomer() ? Favorite::artistIdsFor((int) Auth::id()) : [],
            'hasFilters'  => $this->hasActiveFilters($filters),
        ]);
    }

    /** @param array<string, mixed> $filters */
    private function titleFor(array $filters): string
    {
        if ($filters['q'] !== '') {
            return 'Search: ' . $filters['q'];
        }
        if ($filters['category'] !== '') {
            return ucfirst(str_replace('-', ' ', $filters['category'])) . ' artists';
        }
        return 'Browse artists';
    }

    /** @param array<string, mixed> $filters */
    private function hasActiveFilters(array $filters): bool
    {
        return $filters['q'] !== ''
            || $filters['category'] !== ''
            || $filters['city'] !== ''
            || $filters['min_rating'] > 0
            || $filters['max_price'] > 0
            || $filters['verified']
            || $filters['instant'];
    }

    public function show(Request $request): never
    {
        $artist = $this->abortIfMissing(
            Artist::findBySlug((string) $request->param('slug', '')),
            'artist'
        );

        $artistId = (int) $artist['id'];
        $services = Service::forArtist($artistId, true);

        // Open the booking panel on the first service so the page is useful
        // immediately rather than making people choose before seeing anything.
        $firstService = $services[0] ?? null;

        $this->view('pages/artist-profile', [
            'title'  => $artist['display_name'],
            'metaDescription' => excerpt($artist['headline'] ?? $artist['bio'] ?? '', 155),
            'artist'   => $artist,
            'services' => $services,
            'portfolio' => Portfolio::forArtist($artistId, 12),
            'reviews'   => Review::forArtist($artistId, 8),
            'reviewCount' => Review::countForArtist($artistId),
            'distribution' => Review::distribution($artistId),
            'schedule'  => AvailabilityService::weekSummary($artistId),
            'nextAvailable' => $firstService === null
                ? null
                : AvailabilityService::nextAvailableDate(
                    $artistId,
                    (int) $firstService['duration_min'],
                    (int) $firstService['buffer_min']
                ),
            'isFavorite' => Auth::isCustomer() && Favorite::exists((int) Auth::id(), $artistId),
        ]);
    }

    /**
     * The artist's full portfolio.
     *
     * The profile page shows a preview; this is the whole gallery, on its own
     * URL so it can be linked to and shared.
     */
    public function portfolio(Request $request): never
    {
        $artist = $this->abortIfMissing(
            Artist::findBySlug((string) $request->param('slug', '')),
            'artist'
        );

        $this->view('pages/artist-portfolio', [
            'title'  => $artist['display_name'] . "'s portfolio",
            'metaDescription' => 'Recent work by ' . $artist['display_name']
                . (empty($artist['city']) ? '' : ' in ' . $artist['city']) . '.',
            'artist'    => $artist,
            'portfolio' => Portfolio::publicForArtist((int) $artist['id']),
        ]);
    }

    /** Extra reviews for the "load more" link (works without JavaScript). */
    public function reviews(Request $request): never
    {
        $artist = $this->abortIfMissing(
            Artist::findBySlug((string) $request->param('slug', '')),
            'artist'
        );

        $page    = max(1, (int) $request->query('page', 1));
        $perPage = 20;

        $this->view('pages/artist-reviews', [
            'title'   => 'Reviews for ' . $artist['display_name'],
            'artist'  => $artist,
            'reviews' => Review::forArtist((int) $artist['id'], $perPage, ($page - 1) * $perPage),
            'total'   => Review::countForArtist((int) $artist['id']),
            'distribution' => Review::distribution((int) $artist['id']),
            'page'    => $page,
            'pages'   => (int) max(1, ceil(Review::countForArtist((int) $artist['id']) / $perPage)),
        ]);
    }

    /**
     * Toggle a saved artist. Answers JSON for the heart button, and redirects
     * back for the no-JavaScript path.
     */
    public function toggleFavorite(Request $request): never
    {
        if (!Auth::check()) {
            if ($request->wantsJson()) {
                Response::json(['error' => 'Sign in to save artists.'], 401);
            }
            $this->failure('Sign in to save artists.', '/login');
        }

        if (!Auth::isCustomer()) {
            if ($request->wantsJson()) {
                Response::json(['error' => 'Only client accounts can save artists.'], 403);
            }
            $this->failure('Only client accounts can save artists.', '/');
        }

        $artistId = $request->intParam('id');
        $artist   = Artist::findByUserId($artistId, true);

        if ($artist === null) {
            if ($request->wantsJson()) {
                Response::json(['error' => 'Artist not found.'], 404);
            }
            $this->abort(404);
        }

        $favorited = Favorite::toggle((int) Auth::id(), $artistId);

        if ($request->wantsJson()) {
            Response::json(['favorited' => $favorited]);
        }

        $this->back();
    }
}
