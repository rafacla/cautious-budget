<?php

namespace App\Http\Livewire;

use Livewire\Component;
use App\Models\Bank;

class Banks extends Component
{
    public $banks, $title, $short_name, $full_name, $country, $icon, $bank_id;
    public $isOpen = 0;


    /**
     * The attributes that are mass assignable.
     *
     * @var array
    */
    public function render()
    {
        $this->banks = Bank::all();
        return view('livewire.banks.list');
    }

    /**
     * The attributes that are mass assignable.
     *
     * @var array
    */
    public function create()
    {
        $this->resetInputFields();
        $this->openModal();
    }
  
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    public function openModal()
    {
        $this->isOpen = true;
    }

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    public function closeModal()
    {
        $this->isOpen = false;
    }
  
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    private function resetInputFields(){
        $this->short_name = '';
        $this->full_name = '';
        $this->country = '';
        $this->icon = '';
        $this->bank_id = '';
    }

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    public function store()
    {
        $this->validate([
            'short_name' => 'required',
            'full_name' => 'required',
            'country' => 'required',
        ]);
   
        Bank::updateOrCreate(['id' => $this->bank_id], [
            'short_name' => $this->short_name,
            'full_name' => $this->full_name,
            'country' => $this->country,
            'icon' => $this->icon
        ]);
  
        session()->flash('message', 
            $this->bank_id ? 'Bank updated Successfully.' : 'Bank created Successfully.');
  
        $this->closeModal();
        $this->resetInputFields();
    }

    /**
     * The attributes that are mass assignable.
     *
     * @var array
    */
    public function edit($id)
    {
        $bank = Bank::findOrFail($id);
        $this->bank_id = $id;
        $this->short_name = $bank->short_name;
        $this->full_name = $bank->full_name;
        $this->country = $bank->country;
        $this->icon = $bank->icon;
    
        $this->openModal();
    }

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    public function delete($id)
    {
        Bank::find($id)->delete();
        session()->flash('message', 'Bank deleted Successfully.');
    }
}
