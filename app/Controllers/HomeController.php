<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Request;
use App\Models\Artist;
use App\Models\Booking;
use App\Models\Favorite;
use App\Models\Plan;
use App\Models\Service;
use App\Models\User;

final class HomeController extends Controller
{
    public function index(Request $request): never
    {
        $stats = Booking::platformStats();

        $this->view('pages/home', [
            'title'       => null,
            'featured'    => Artist::featured(6),
            'categories'  => Service::categoriesWithCounts(),
            'favoriteIds' => Auth::isCustomer() ? Favorite::artistIdsFor((int) Auth::id()) : [],
            'artistCount' => User::countByRole('artist'),
            'bookingCount'=> (int) ($stats['completed'] ?? 0),
            'cities'      => array_slice(Artist::cities(), 0, 6),
        ]);
    }

    /** Landing page pitching the platform to artists. */
    public function forArtists(Request $request): never
    {
        $this->view('pages/for-artists', [
            'title' => 'For artists',
            'metaDescription' => 'List your services, manage your calendar and get booked. Free to start, with paid plans for more listings and better placement.',
            'plans' => Plan::all(),
        ]);
    }

    public function pricing(Request $request): never
    {
        $cycle = $request->query('cycle') === 'yearly' ? 'yearly' : 'monthly';

        $this->view('pages/pricing', [
            'title' => 'Plans and pricing',
            'metaDescription' => 'Simple plans for artists on PolishedPro. Start free, upgrade when your calendar fills up.',
            'plans' => Plan::all(),
            'cycle' => $cycle,
        ]);
    }

    public function categories(Request $request): never
    {
        $this->view('pages/categories', [
            'title'      => 'Services',
            'categories' => Service::categoriesWithCounts(),
            'trending'   => Service::trending(8),
        ]);
    }

    /** Static content pages, kept in one action so routes stay tidy. */
    public function page(Request $request): never
    {
        $slug = (string) $request->param('slug', '');

        $pages = [
            'help'    => 'Help centre',
            'terms'   => 'Terms of service',
            'privacy' => 'Privacy policy',
        ];

        if (!isset($pages[$slug])) {
            $this->abort(404);
        }

        $this->view('pages/' . $slug, ['title' => $pages[$slug]]);
    }
}
