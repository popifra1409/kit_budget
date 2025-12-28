<?php

namespace App\Livewire;

use App\Models\ParametresStructure;
use Livewire\Component;

class CustomTopbar extends Component
{
    public $structure;

    public function mount()
    {
        $this->structure = ParametresStructure::getParametres();
    }

    public function render()
    {
        return view('livewire.custom-topbar');
    }
}
