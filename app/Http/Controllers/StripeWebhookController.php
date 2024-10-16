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
        Log::info('Webhook received', ['payload' => $request->all()]);
        
        $payload = $request->getContent();
        $sig_header = $request->header('Stripe-Signature');
        $webhook_secret = env('STRIPE_WEBHOOK_SECRET');

        try {
            $event = Webhook::constructEvent($payload, $sig_header, $webhook_secret);
            Log::info('Webhook verified', ['event_type' => $event->type]);
        } catch (\UnexpectedValueException $e) {
            Log::error('Webhook error: Invalid payload', ['error' => $e->getMessage()]);
            return response()->json(['error' => 'Invalid payload'], 400);
        } catch (SignatureVerificationException $e) {
            Log::error('Webhook error: Invalid signature', ['error' => $e->getMessage()]);
            return response()->json(['error' => 'Invalid signature'], 400);
        }

        if ($event->type === 'checkout.session.completed') {
            $session = $event->data->object;
            $this->handleSuccessfulPayment($session);
        } else {
            Log::info('Unhandled event type', ['event_type' => $event->type]);
        }

        return response()->json(['status' => 'success']);
    }

    private function handleSuccessfulPayment($session)
    {
        Log::info('Handling successful payment', ['session_id' => $session->id, 'customer' => $session->customer]);

        $user = User::where('email', $session->customer_details->email)->first();

        if ($user) {
            Log::info('User found', ['user_id' => $user->id]);
            $user->has_paid = true;
            $user->stripe_customer_id = $session->customer;
            $user->save();
            Log::info('User updated', [
                'user_id' => $user->id,
                'has_paid' => $user->has_paid,
                'stripe_customer_id' => $user->stripe_customer_id
            ]);
        } else {
            Log::warning('User not found for email', ['email' => $session->customer_details->email]);
        }
    }
}
