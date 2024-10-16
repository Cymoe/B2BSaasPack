<x-app-layout>
   
    <div class="flex justify-center">
        <div class="inline-flex items-center bg-base-300 text-base-content mt-10 px-6 py-2 rounded-full shadow-lg animate-sparkle">
            <span class="font-bold text-sm">✨ Launch discount — $100 OFF ✨</span>
        </div>
    </div>

    <style>
        @keyframes sparkle {
            0% { opacity: 1; transform: scale(1); }
            50% { opacity: 0.9; transform: scale(1.02); }
            100% { opacity: 1; transform: scale(1); }
        }
        .animate-sparkle {
            animation: sparkle 2s ease-in-out infinite;
        }
        .bg-info-dark {
            background-color: hsl(var(--in) / 0.8);
        }
        .text-info-lighter {
            color: hsl(var(--inc) / 0.9);
        }
    </style>

    <div class="py-12">
        <div class="max-w-6xl mx-auto sm:px-6 lg:px-8">
            <div class="text-center mb-12">
                <h1 class="text-4xl font-bold mb-4">Powerful plans for every stage of your journey</h1>
                <p class="text-xl text-gray-600">Join our exclusive test run and secure lifetime access for a one-time fee!</p>
                <p class="text-xl text-gray-600">Choose the plan that fits your needs</p>
            </div>

            <div class="flex flex-col md:flex-row justify-center space-y-4 md:space-y-0 md:space-x-12"> <!-- Added space between cards -->
                @foreach($products as $product)
                    <div class="card bg-base-100 shadow-xl {{ $product['is_popular'] ?? false ? 'border-2 border-primary' : '' }} w-full md:w-2/5"> <!-- Reduced width to 2/5 -->
                        <div class="card-body">
                            @if($product['is_popular'] ?? false)
                                <div class="absolute top-0 right-0 mt-4 mr-4">
                                    <span class="badge badge-primary font-bold">BUSY BUILDER'S CHOICE</span>
                                </div>
                            @endif
                            <div class="flex items-baseline mb-2">
                                @if(isset($product['original_price']))
                                    <span class="text-lg line-through text-gray-500 mr-2">${{ number_format($product['original_price'], 0) }}</span>
                                @endif
                                <span class="text-5xl font-extrabold">${{ number_format($product['price'], 0) }}</span>
                                <span class="text-gray-500 ml-2">USD</span>
                            </div>
                            
                            @if(isset($product['features']) && is_array($product['features']))
                                <ul class="space-y-2 my-4">
                                    @foreach($product['features'] as $feature)
                                        <li class="flex items-center">
                                            <svg class="w-5 h-5 text-success mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                                            <span>{{ $feature }}</span>
                                        </li>
                                    @endforeach
                                </ul>
                            @endif
                            
                            <div class="card-actions mt-4">
                                <a href="{{ route('checkout', $product['id']) }}" class="btn btn-primary btn-block">
                                    Get {{ $product['name'] }} 
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 ml-2" viewBox="0 0 20 20" fill="currentColor">
                                        <path fill-rule="evenodd" d="M10.293 5.293a1 1 0 011.414 0l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414-1.414L12.586 11H5a1 1 0 110-2h7.586l-2.293-2.293a1 1 0 010-1.414z" clip-rule="evenodd" />
                                    </svg>
                                </a>
                            </div>
                            <p class="text-center text-base font-semibold text-gray-700 mt-2">One-time payment, then <u class="font-bold">it's yours forever</u></p>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </div>
</x-app-layout>
