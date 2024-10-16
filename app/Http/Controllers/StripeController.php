<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Stripe\Stripe;
use Stripe\Price;
use Stripe\Checkout\Session;
use Stripe\Exception\ApiErrorException;

class StripeController extends Controller
{
    public function __construct()
    {
        Stripe::setApiKey(env('STRIPE_SECRET'));
    }

    public function productList()
    {
        try {
            $prices = Price::all([
                'active' => true,
                'type' => 'one_time',
                'expand' => ['data.product'],
                'limit' => 100,
            ]);

            $formattedProducts = array_map(function ($price) {
                return [
                    'id' => $price->id,
                    'name' => $price->product->name,
                    'description' => $price->product->description,
                    'price' => $price->unit_amount / 100,
                    'currency' => strtoupper($price->currency),
                ];
            }, $prices->data);

            return view('products', ['products' => $formattedProducts]);
        } catch (ApiErrorException $e) {
            \Log::error('Stripe API Error: ' . $e->getMessage());
            return back()->with('error', 'Unable to fetch products. Please try again later.');
        }
    }

    public function checkout($priceId)
    {
        try {
            $user = auth()->user();
            $sessionParams = [
                'payment_method_types' => ['card'],
                'line_items' => [[
                    'price' => $priceId,
                    'quantity' => 1,
                ]],
                'mode' => 'payment',
                'success_url' => route('success') . '?session_id={CHECKOUT_SESSION_ID}',
                'cancel_url' => route('cancel'),
            ];

            if ($user->stripe_customer_id) {
                $sessionParams['customer'] = $user->stripe_customer_id;
            } else {
                // Create a new Stripe customer
                $stripeCustomer = \Stripe\Customer::create([
                    'email' => $user->email,
                ]);
                $user->stripe_customer_id = $stripeCustomer->id;
                $user->save();
                $sessionParams['customer'] = $stripeCustomer->id;
            }

            $session = Session::create($sessionParams);

            return redirect($session->url);
        } catch (ApiErrorException $e) {
            \Log::error('Stripe API Error: ' . $e->getMessage());
            return back()->with('error', 'Unable to create checkout session. Please try again later.');
        }
    }

    public function success(Request $request)
    {
        $user = auth()->user();
        $user->has_paid = true;

        $sessionId = $request->get('session_id');

        if ($sessionId) {
            $session = Session::retrieve($sessionId);
            if (!$user->stripe_customer_id) {
                $user->stripe_customer_id = $session->customer;
            }
        }

        $user->save();

        return redirect()->route('dashboard')->with('success', 'Payment successful! Welcome to the premium area.');
    }

    public function cancel()
    {
        return view('cancel');
    }

    public function billingPortal()
    {
        $user = auth()->user();
        
        if (!$user) {
            return response()->json(['error' => 'User not authenticated'], 401);
        }
        
        \Log::info('Billing Portal Access', [
            'user_id' => $user->id,
            'email' => $user->email,
            'stripe_customer_id' => $user->stripe_customer_id
        ]);

        if (!$user->stripe_customer_id) {
            // Create a new Stripe customer if the user doesn't have one
            try {
                $stripeCustomer = \Stripe\Customer::create([
                    'email' => $user->email,
                ]);
                $user->stripe_customer_id = $stripeCustomer->id;
                $user->save();
                \Log::info('Created new Stripe customer', ['stripe_customer_id' => $stripeCustomer->id]);
            } catch (\Exception $e) {
                \Log::error('Failed to create Stripe customer: ' . $e->getMessage());
                return response()->json(['error' => 'Failed to create Stripe customer'], 500);
            }
        }

        try {
            $session = \Stripe\BillingPortal\Session::create([
                'customer' => $user->stripe_customer_id,
                'return_url' => route('dashboard'),
            ]);

            return response()->json(['url' => $session->url]);
        } catch (\Exception $e) {
            \Log::error('Failed to create Stripe Billing Portal session: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to create Billing Portal session'], 500);
        }
    }
}
