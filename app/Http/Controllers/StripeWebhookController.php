<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Stripe\Webhook;
use Stripe\Exception\SignatureVerificationException;
use Illuminate\Support\Facades\Log;

class StripeWebhookController extends Controller
{
    public function handleWebhook(Request $request)
    {
        $payload = $request->getContent();
        $sig_header = $request->header('Stripe-Signature');
        $event = null;

        try {
            $event = \Stripe\Webhook::constructEvent(
                $payload, $sig_header, config('services.stripe.webhook_secret')
            );
        } catch(\UnexpectedValueException $e) {
            return response()->json(['error' => 'Invalid payload'], 400);
        } catch(\Stripe\Exception\SignatureVerificationException $e) {
            return response()->json(['error' => 'Invalid signature'], 400);
        }

        if ($event->type === 'payment_intent.succeeded') {
            $paymentIntent = $event->data->object;
            $this->handleSuccessfulPayment($paymentIntent);
        } else {
            Log::info('Unhandled event type', ['event_type' => $event->type]);
        }

        return response()->json(['status' => 'success']);
    }

    private function handleSuccessfulPayment($paymentIntent)
    {
        Log::info('Handling successful payment', ['payment_intent_id' => $paymentIntent->id]);

        $user = User::where('stripe_customer_id', $paymentIntent->customer)->first();

        if ($user) {
            Log::info('User found', ['user_id' => $user->id]);
            $user->has_paid = true;
            $user->save();
            Log::info('User updated', [
                'user_id' => $user->id,
                'has_paid' => $user->has_paid,
                'stripe_customer_id' => $user->stripe_customer_id
            ]);
        } else {
            Log::warning('User not found for Stripe customer', ['stripe_customer_id' => $paymentIntent->customer]);
        }
    }
}
