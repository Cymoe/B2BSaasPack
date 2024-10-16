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
            \Stripe\Stripe::setApiKey(env('STRIPE_SECRET'));

            $prices = \Stripe\Price::all([
                'active' => true,
                'type' => 'one_time',
                'expand' => ['data.product'],
                'limit' => 100,
            ]);

            $products = array_values(array_filter(array_map(function ($price) {
                $product = $price->product;
                if (!$product->active) {
                    return null;
                }
                return [
                    'id' => $price->id,
                    'name' => $product->name,
                    'price' => $price->unit_amount / 100,
                    'original_price' => isset($product->metadata['original_price']) ? intval($product->metadata['original_price']) : null,
                    'features' => isset($product->metadata['features']) ? explode(',', $product->metadata['features']) : [],
                    'is_popular' => isset($product->metadata['is_popular']) ? filter_var($product->metadata['is_popular'], FILTER_VALIDATE_BOOLEAN) : false,
                    'order' => isset($product->metadata['order']) ? intval($product->metadata['order']) : 999, // Default to a high number if not set
                ];
            }, $prices->data)));

            // Sort products based on the 'order' field
            usort($products, function($a, $b) {
                return $a['order'] - $b['order'];
            });

            return view('products', compact('products'));
        } catch (\Exception $e) {
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
                'customer' => $user->stripe_customer_id,
                'payment_method_types' => ['card'],
                'line_items' => [[
                    'price' => $priceId,
                    'quantity' => 1,
                ]],
                'mode' => 'payment',
                'success_url' => route('payment.process') . '?session_id={CHECKOUT_SESSION_ID}',
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

    public function processPayment(Request $request)
    {
        $sessionId = $request->get('session_id');
        $user = auth()->user();

        try {
            $session = \Stripe\Checkout\Session::retrieve($sessionId);
            $paymentIntent = \Stripe\PaymentIntent::retrieve($session->payment_intent);

            // Check if payment is successful
            if ($paymentIntent->status === 'succeeded') {
                // Retrieve the line items from the session
                $lineItems = \Stripe\Checkout\Session::allLineItems($sessionId);
                $lineItem = $lineItems->data[0];
                $priceId = $lineItem->price->id;
                $quantity = $lineItem->quantity;
                $unitAmount = $lineItem->price->unit_amount;
                $currency = $lineItem->price->currency;

                // Create a finalized invoice
                $invoice = \Stripe\Invoice::create([
                    'customer' => $user->stripe_customer_id,
                    'auto_advance' => false, // Prevent automatic finalization
                    'collection_method' => 'charge_automatically',
                ]);

                // Add the line item to the invoice
                \Stripe\InvoiceItem::create([
                    'customer' => $user->stripe_customer_id,
                    'price' => $priceId,
                    'quantity' => $quantity,
                    'invoice' => $invoice->id,
                ]);

                // Finalize the invoice
                $invoice->finalizeInvoice();

                // Mark the invoice as paid
                $invoice->pay(['paid_out_of_band' => true]);

                // Handle successful payment
                $user->has_paid = true;
                $user->save();

                // You may want to create an order or update user's subscription status here

                return redirect()->route('dashboard')->with('success', 'Payment successful! Invoice created. Welcome to the premium area.');
            } else {
                // Payment not successful, you might want to handle this case
                return redirect()->route('products')->with('error', 'Payment was not successful. Please try again.');
            }
        } catch (\Exception $e) {
            \Log::error('Payment processing failed: ' . $e->getMessage());
            return redirect()->route('products')->with('error', 'Payment processing failed. Please contact support.');
        }
    }
}
