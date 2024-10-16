<?php

namespace App\Livewire;

use Livewire\Component;

class ThemeSelector extends Component
{
    public $currentTheme = 'light';
    public $themes = [
        "light", "dark", "cupcake", "bumblebee", "emerald", "corporate", "synthwave",
        "retro", "cyberpunk", "valentine", "halloween", "garden", "forest", "aqua",
        "lofi", "pastel", "fantasy", "wireframe", "black", "luxury", "dracula",
        "cmyk", "autumn", "business", "acid", "lemonade", "night", "coffee", "winter"
    ];

    public function setTheme($theme)
    {
        $this->currentTheme = $theme;
        $this->dispatch('themeChanged', $theme);
    }

    public function render()
    {
        return view('livewire.theme-selector');
    }
}
