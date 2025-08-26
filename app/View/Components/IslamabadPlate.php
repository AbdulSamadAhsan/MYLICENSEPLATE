<?php

namespace App\View\Components;

use Closure;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

class IslamabadPlate extends Component
{
    /**
     * Create a new component instance.
     */
     public $plate;
    public function __construct($plate)
    {
         $this->plate = $plate;
    }

    /**
     * Get the view / contents that represent the component.
     */
    public function render(): View|Closure|string
    {
        return view('components.islamabad-plate');
    }
}
