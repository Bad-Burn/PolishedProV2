<?php

declare(strict_types=1);

namespace App\Controllers\Customer;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Request;
use App\Models\Booking;
use App\Models\Favorite;
use App\Models\Review;

final class DashboardController extends Controller
{
    /** Bookings home, split by tab. */
    public function index(Request $request): never
    {
        $customerId = (int) Auth::id();

        $scope = (string) $request->query('tab', 'upcoming');
        if (!in_array($scope, ['upcoming', 'past', 'cancelled'], true)) {
            $scope = 'upcoming';
        }

        $stats = Booking::customerStats($customerId);

        $this->view('customer/bookings', [
            'title'    => 'My bookings',
            'bookings' => Booking::forUser($customerId, 'customer', $scope),
            'scope'    => $scope,
            'stats'    => $stats,
            'counts'   => [
                'upcoming'  => (int) ($stats['upcoming'] ?? 0),
                'past'      => (int) ($stats['completed'] ?? 0),
            ],
            'savedCount' => Favorite::countFor($customerId),
        ]);
    }

    public function saved(Request $request): never
    {
        $customerId = (int) Auth::id();

        $this->view('customer/saved', [
            'title'   => 'Saved artists',
            'artists' => Favorite::forCustomer($customerId),
            'favoriteIds' => Favorite::artistIdsFor($customerId),
            'savedCount'  => Favorite::countFor($customerId),
        ]);
    }

    public function reviews(Request $request): never
    {
        $customerId = (int) Auth::id();

        $this->view('customer/reviews', [
            'title'   => 'My reviews',
            'reviews' => Review::byCustomer($customerId),
            'savedCount' => Favorite::countFor($customerId),
        ]);
    }
}
