<div>
    <select wire:model="currentTheme" wire:change="setTheme($event.target.value)" class="select select-bordered w-full max-w-xs">
        @foreach($themes as $theme)
            <option value="{{ $theme }}">{{ ucfirst($theme) }}</option>
        @endforeach
    </select>
</div>
