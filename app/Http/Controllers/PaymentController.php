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
            return redirect(env('FRONTEND_URL', 'https://ptntfront-production.up.railway.app') . '/profile')->with('error', 'Invalid payment session');
        }

        try {
            // Retrieve the session from Stripe
            $session = Session::retrieve($sessionId);

            if ($session->payment_status === 'paid') {
                $status = $this->processPayment($session);
                
                $redirectUrl = env('FRONTEND_URL', 'https://ptntfront-production.up.railway.app') . '/profile?payment_success=1';
                return redirect($redirectUrl)->with('success', 'Premium subscription activated successfully!');
            }

            return redirect(env('FRONTEND_URL', 'https://ptntfront-production.up.railway.app') . '/profile')->with('error', 'Payment verification failed');
        } catch (\Exception $e) {
            return redirect(env('FRONTEND_URL', 'https://ptntfront-production.up.railway.app') . '/profile')->with('error', 'Payment processing error: ' . $e->getMessage());
        }
    }

    /**
     * Handle cancelled payment
     */
    public function cancel()
    {
        return redirect(env('FRONTEND_URL', 'https://ptntfront-production.up.railway.app') . '/profile')->with('info', 'Payment cancelled');
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
                    $this->processPayment($session);
                }
            }

            return response()->json(['status' => 'success']);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }

    /**
     * Process the payment and update user status
     * Returns true if processed now, false if already processed
     */
    private function processPayment($session)
    {
        // Check if this session was already processed
        $existingPayment = \App\Models\Payment::where('stripe_session_id', $session->id)->first();
        if ($existingPayment) {
            return false;
        }

        $userId = $session->metadata->user_id;
        $user = User::find($userId);

        if (!$user) {
            throw new \Exception("User not found for ID: {$userId}");
        }

        // Create payment record
        \App\Models\Payment::create([
            'user_id' => $user->id,
            'stripe_session_id' => $session->id,
            'amount' => $session->amount_total,
            'currency' => $session->currency,
            'status' => $session->payment_status,
        ]);

        // Update user premium status
        $this->updatePremiumStatus($user);

        return true;
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
