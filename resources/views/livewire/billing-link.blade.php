<div>
    <button wire:click="getBillingPortalUrl" class="w-full text-start">
        <x-dropdown-link>
            {{ __('Billing') }}
        </x-dropdown-link>
    </button>
</div>

<script>
    document.addEventListener('livewire:initialized', () => {
        Livewire.on('openUrlInNewTab', (event) => {
            window.open(event.url, '_blank');
        });
        Livewire.on('showError', (event) => {
            alert(event.message);
        });
    });
</script>
