<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Dashboard') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900">
                    @if(auth()->user()->has_paid)
                        {{ __("Welcome to the premium area! You now have access to all features.") }}
                    @else
                        {{ __("You're logged in, but you don't have premium access yet.") }}
                        <a href="{{ route('products') }}" class="text-blue-500 hover:text-blue-700">Upgrade now</a>
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
