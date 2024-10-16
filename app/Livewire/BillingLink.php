<?php

namespace App\Livewire;

use Livewire\Component;
use Illuminate\Support\Facades\Auth;
use Stripe\Stripe;
use Stripe\Customer;
use Stripe\BillingPortal\Session;

class BillingLink extends Component
{
    public function getBillingPortalUrl()
    {
        $user = Auth::user();

        if (!$user) {
            $this->dispatch('showError', message: 'User not authenticated');
            return;
        }

        \Log::info('Getting Billing Portal URL', [
            'user_id' => $user->id,
            'stripe_customer_id' => $user->stripe_customer_id,
            'email' => $user->email
        ]);

        Stripe::setApiKey(config('services.stripe.secret'));

        try {
            if (!$user->stripe_customer_id) {
                $stripeCustomer = Customer::create([
                    'email' => $user->email,
                ]);
                $user->stripe_customer_id = $stripeCustomer->id;
                $user->save();
            }

            $session = Session::create([
                'customer' => $user->stripe_customer_id,
                'return_url' => route('dashboard'),
            ]);

            $this->dispatch('openUrlInNewTab', url: $session->url);
        } catch (\Exception $e) {
            \Log::error('Stripe error: ' . $e->getMessage());
            $this->dispatch('showError', message: 'Failed to create Billing Portal session');
        }
    }

    public function render()
    {
        return view('livewire.billing-link');
    }
}
