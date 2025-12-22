<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Stripe\Stripe;
use Stripe\Checkout\Session;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;

class PaymentController extends Controller
{
    public function __construct()
    {
        // Set Stripe API key
        Stripe::setApiKey(config('services.stripe.secret'));
    }

    /**
     * Create a Stripe checkout session for premium subscription
     */
    public function createCheckoutSession(Request $request)
    {
        try {
            $user = Auth::user();

            // Create Stripe checkout session
            $session = Session::create([
                'payment_method_types' => ['card'],
                'line_items' => [
                    [
                        'price_data' => [
                            'currency' => 'usd',
                            'product_data' => [
                                'name' => 'Premium Subscription - 1 Month',
                                'description' => 'One month premium access',
                            ],
                            'unit_amount' => 999, // $9.99 in cents
                        ],
                        'quantity' => 1,
                    ]
                ],
                'mode' => 'payment',
                'success_url' => url('/payment/success?session_id={CHECKOUT_SESSION_ID}'),
                'cancel_url' => url('/payment/cancel'),
                'client_reference_id' => $user->id,
                'metadata' => [
                    'user_id' => $user->id,
                ],
            ]);

            return response()->json([
                'sessionId' => $session->id,
                'url' => $session->url
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Handle successful payment
     */
    public function success(Request $request)
    {
        $sessionId = $request->query('session_id');

        if (!$sessionId) {
            return redirect('/profile')->with('error', 'Invalid payment session');
        }

        try {
            // Retrieve the session from Stripe
            $session = Session::retrieve($sessionId);

            if ($session->payment_status === 'paid') {
                $userId = $session->metadata->user_id;
                $user = User::find($userId);

                if ($user) {
                    // Update user premium status
                    $this->updatePremiumStatus($user);

                    return redirect('https://ptntfront-production.up.railway.app/profile')->with('success', 'Premium subscription activated successfully!');
                }
            }

            return redirect('/profile')->with('error', 'Payment verification failed');
        } catch (\Exception $e) {
            return redirect('/profile')->with('error', 'Payment processing error: ' . $e->getMessage());
        }
    }

    /**
     * Handle cancelled payment
     */
    public function cancel()
    {
        return redirect('/profile')->with('info', 'Payment cancelled');
    }

    /**
     * Webhook handler for Stripe events
     */
    public function webhook(Request $request)
    {
        $payload = $request->getContent();
        $sigHeader = $request->header('Stripe-Signature');
        $webhookSecret = config('services.stripe.webhook_secret');

        try {
            $event = \Stripe\Webhook::constructEvent(
                $payload,
                $sigHeader,
                $webhookSecret
            );

            // Handle the checkout.session.completed event
            if ($event->type === 'checkout.session.completed') {
                $session = $event->data->object;

                if ($session->payment_status === 'paid') {
                    $userId = $session->metadata->user_id;
                    $user = User::find($userId);

                    if ($user) {
                        $this->updatePremiumStatus($user);
                    }
                }
            }

            return response()->json(['status' => 'success']);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }

    /**
     * Update user premium status
     */
    private function updatePremiumStatus(User $user)
    {
        $currentPremiumUntil = $user->premium_until ? Carbon::parse($user->premium_until) : Carbon::now();

        // If premium already expired or not set, start from today
        if ($currentPremiumUntil->isPast()) {
            $newPremiumUntil = Carbon::now()->addMonth();
        } else {
            // If still premium, extend by one month from current expiry
            $newPremiumUntil = $currentPremiumUntil->addMonth();
        }

        $user->premium = true;
        $user->premium_until = $newPremiumUntil;
        $user->save();
    }
}
