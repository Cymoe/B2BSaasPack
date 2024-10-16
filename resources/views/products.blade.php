<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Products') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900">
                    @if(session('error'))
                        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative" role="alert">
                            <strong class="font-bold">Error!</strong>
                            <span class="block sm:inline">{{ session('error') }}</span>
                        </div>
                    @endif

                    @if(isset($products) && count($products) > 0)
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                            @foreach($products as $product)
                                <div class="border rounded-lg p-6 shadow-sm">
                                    <h3 class="text-lg font-semibold">{{ $product['name'] }}</h3>
                                    <p class="text-gray-600 mt-2">{{ $product['description'] }}</p>
                                    <p class="text-xl font-bold mt-4">{{ $product['currency'] }} {{ number_format($product['price'], 2) }}</p>
                                    <a href="{{ route('checkout', $product['id']) }}" class="mt-4 inline-block bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded">
                                        Buy Now
                                    </a>
                                    <button class="btn btn-primary">Click me</button>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <p>No products available at the moment.</p>
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
