<?php
/**
 * SmartPark - Driver Dashboard (dashboard.php)
 */
require_once 'includes/functions.php';
requireLogin('login.php');

// All upcoming bookings (demo data)
$allUpcoming = [
    ['id'=>3,'park'=>'Bondi Junction Carpark',   'suburb'=>'Bondi Junction','spot'=>'A-03',
     'start'=>date('Y-m-d H:i', strtotime('+1 day')),
     'end'  =>date('Y-m-d H:i', strtotime('+1 day +2 hours')),'status'=>'confirmed','total'=>14.00],
    ['id'=>5,'park'=>'Chatswood Station Parking','suburb'=>'Chatswood',    'spot'=>'B-02',
     'start'=>date('Y-m-d H:i', strtotime('+2 days')),
     'end'  =>date('Y-m-d H:i', strtotime('+2 days +1 hour')),'status'=>'confirmed','total'=>4.50],
];

$pastBookings = [
    ['id'=>1,'park'=>'Sydney CBD Parking Centre',   'suburb'=>'Sydney',     'spot'=>'A-05',
     'start'=>date('Y-m-d H:i', strtotime('-5 days')),
     'end'  =>date('Y-m-d H:i', strtotime('-5 days +2 hours')),'status'=>'completed','total'=>16.00],
    ['id'=>2,'park'=>'Parramatta Westfield Parking','suburb'=>'Parramatta','spot'=>'A-12',
     'start'=>date('Y-m-d H:i', strtotime('-2 days')),
     'end'  =>date('Y-m-d H:i', strtotime('-2 days +3 hours')),'status'=>'completed','total'=>15.00],
];

// Handle cancel booking POST
$cancelMsg   = '';
$cancelledId = 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cancel_booking'])) {
    if (empty($_POST['csrf_token']) || !verifyCsrfToken($_POST['csrf_token'])) {
        $cancelMsg = 'error:Invalid request. Please try again.';
    } else {
        $cancelledId = intval($_POST['booking_id']);

        // Find the cancelled booking and move it to past bookings
        foreach ($allUpcoming as $b) {
            if ($b['id'] === $cancelledId) {
                $b['status'] = 'cancelled';
                $pastBookings[] = $b;  // move to history
                break;
            }
        }

        $cancelMsg = 'success:Booking #' . $cancelledId . ' has been cancelled successfully.';
    }
}

// Remove the cancelled booking from upcoming
$upcomingBookings = array_filter($allUpcoming, fn($b) => $b['id'] !== $cancelledId);

$totalSpent    = array_sum(array_column(array_merge($upcomingBookings, $pastBookings), 'total'));
$totalBookings = count($upcomingBookings) + count($pastBookings);

$pageTitle = 'My Dashboard';
$activeNav = 'dash';
require 'includes/header.php';
?>