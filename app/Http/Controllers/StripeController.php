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
            $price = \Stripe\Price::retrieve($priceId);
            
            if (!$user->stripe_customer_id) {
                $stripeCustomer = \Stripe\Customer::create([
                    'email' => $user->email,
                ]);
                $user->stripe_customer_id = $stripeCustomer->id;
                $user->save();
            }

            $sessionParams = [
                'payment_method_types' => ['card'],
                'mode' => 'setup',
                'customer' => $user->stripe_customer_id,
                'setup_intent_data' => [
                    'metadata' => [
                        'price_id' => $priceId,
                    ],
                ],
                'success_url' => route('success') . '?session_id={CHECKOUT_SESSION_ID}',
                'cancel_url' => route('cancel'),
            ];

            $session = \Stripe\Checkout\Session::create($sessionParams);

            return redirect($session->url);
        } catch (\Stripe\Exception\ApiErrorException $e) {
            \Log::error('Stripe API Error: ' . $e->getMessage());
            return back()->with('error', 'Unable to create checkout session. Please try again later.');
        }
    }

    public function success(Request $request)
    {
        $sessionId = $request->get('session_id');
        $user = auth()->user();

        try {
            $session = \Stripe\Checkout\Session::retrieve($sessionId);
            $setupIntent = \Stripe\SetupIntent::retrieve($session->setup_intent);
            $priceId = $setupIntent->metadata['price_id'];
            $price = \Stripe\Price::retrieve($priceId);

            \Log::info('Price details', [
                'price_id' => $priceId,
                'unit_amount' => $price->unit_amount,
                'currency' => $price->currency,
            ]);

            // Attach the payment method to the customer
            $paymentMethod = \Stripe\PaymentMethod::retrieve($setupIntent->payment_method);
            $paymentMethod->attach(['customer' => $user->stripe_customer_id]);

            // Set the payment method as the default for the customer
            \Stripe\Customer::update($user->stripe_customer_id, [
                'invoice_settings' => ['default_payment_method' => $setupIntent->payment_method],
            ]);

            // Create an invoice
            $invoice = \Stripe\Invoice::create([
                'customer' => $user->stripe_customer_id,
                'collection_method' => 'charge_automatically',
                'auto_advance' => false, // Don't finalize the invoice yet
            ]);

            // Add invoice item
            $invoiceItem = \Stripe\InvoiceItem::create([
                'customer' => $user->stripe_customer_id,
                'amount' => $price->unit_amount,
                'currency' => $price->currency,
                'description' => $price->product->name ?? 'Product Purchase',
                'invoice' => $invoice->id,
            ]);

            \Log::info('Invoice item details', [
                'invoice_item_id' => $invoiceItem->id,
                'amount' => $invoiceItem->amount,
                'currency' => $invoiceItem->currency,
            ]);

            // Finalize the invoice
            $invoice = $invoice->finalizeInvoice();

            // Pay the invoice
            $paidInvoice = $invoice->pay(['payment_method' => $setupIntent->payment_method]);

            \Log::info('Invoice details', [
                'invoice_id' => $paidInvoice->id,
                'total' => $paidInvoice->total,
                'amount_due' => $paidInvoice->amount_due,
                'amount_paid' => $paidInvoice->amount_paid,
                'status' => $paidInvoice->status,
            ]);

            $user->has_paid = true;
            $user->save();

            return redirect()->route('dashboard')->with('success', 'Payment successful! Welcome to the premium area.');
        } catch (\Exception $e) {
            \Log::error('Payment or invoice creation failed: ' . $e->getMessage());
            return redirect()->route('products')->with('error', 'Payment failed. Please try again.');
        }
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

    public function getInvoices()
    {
        $user = auth()->user();
        if (!$user->stripe_customer_id) {
            return [];
        }

        try {
            $invoices = \Stripe\Invoice::all([
                'customer' => $user->stripe_customer_id,
                'limit' => 10, // Adjust as needed
            ]);

            return $invoices->data;
        } catch (\Exception $e) {
            \Log::error('Failed to retrieve invoices: ' . $e->getMessage());
            return [];
        }
    }
}
