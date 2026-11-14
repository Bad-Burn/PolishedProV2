<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Config;
use App\Core\Controller;
use App\Core\Request;
use App\Core\Url;
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
            'metaDescription' => 'List your services, manage your calendar and get booked. Start with a 14-day Premium trial. Choose PHP 149 monthly or PHP 1,490 yearly. We never take a commission.',
            'plans' => Plan::all(),
        ]);
    }

    public function pricing(Request $request): never
    {
        $cycle = $request->query('cycle') === 'yearly' ? 'yearly' : 'monthly';

        $this->view('pages/pricing', [
            'title' => 'Plans and pricing',
            'metaDescription' => 'Try PolishedPro Premium free for 14 days, then choose PHP 149 monthly or PHP 1,490 yearly.',
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

    /**
     * Keep crawlers on the public pages. Anything that is not production —
     * a staging copy, a demo — asks not to be indexed at all, so it never
     * competes with the real site in search results.
     */
    public function robots(Request $request): never
    {
        $lines = ['User-agent: *'];

        if (Config::get('app.env') !== 'production') {
            $lines[] = 'Disallow: /';
        } else {
            foreach (['/account', '/artist/', '/artist$', '/admin', '/settings', '/bookings', '/book/', '/notifications', '/login', '/register', '/forgot-password', '/reset-password', '/verify-email'] as $path) {
                // Not Url::to(): it trims the trailing slash that keeps
                // "/artist/" from also blocking the public "/artists" pages.
                $lines[] = 'Disallow: ' . Url::basePath() . $path;
            }
            $lines[] = '';
            $lines[] = 'Sitemap: ' . Url::absolute('/sitemap.xml');
        }

        header('Content-Type: text/plain; charset=utf-8');
        header('Cache-Control: public, max-age=86400');
        echo implode("\n", $lines) . "\n";
        exit;
    }

    public function sitemap(Request $request): never
    {
        $urls = [];

        foreach (['/', '/artists', '/categories', '/for-artists', '/pricing', '/help'] as $path) {
            $urls[] = ['loc' => Url::absolute($path), 'lastmod' => null];
        }

        foreach (Artist::publicSlugs() as $artist) {
            $urls[] = [
                'loc'     => Url::absolute('/artists/' . $artist['slug']),
                'lastmod' => date('Y-m-d', (int) strtotime((string) $artist['updated_at'])),
            ];
        }

        header('Content-Type: application/xml; charset=utf-8');
        header('Cache-Control: public, max-age=3600');

        echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
        foreach ($urls as $url) {
            echo '  <url><loc>' . htmlspecialchars($url['loc'], ENT_XML1 | ENT_QUOTES, 'UTF-8') . '</loc>'
                . ($url['lastmod'] === null ? '' : '<lastmod>' . $url['lastmod'] . '</lastmod>')
                . "</url>\n";
        }
        echo "</urlset>\n";
        exit;
    }
}
