<?php

namespace App\Http\Livewire;

use Livewire\Component;
use Illuminate\Support\Facades\Auth;
use App\Models\Budget;
use App\Helpers\Helper;
use Illuminate\Support\Facades\Route;

class Transactions extends Component
{
    public $modelClass = Budget::Class;
    public $itemClassName = 'Transaction';
    public $currentDate;
    public $items;
    public $itemID;
    public $form = array(
        'budget_value'          => '',
        'subcategory_id'        => '',
    );
    public $isOpen = 0;

    public function mount($year = null,$month = null) {
        if ($year != null)
            $this->currentDate  = mktime(0,0,0,$month,1,$year);
        else
            $this->currentDate = time();
    }

    public function render()
    {   
        if (!isset($this->currentDate))
            $this->currentDate  = time();
        $filter = [
            [
                'filterField'   => 'date',
                'filterAs'      => '>=',
                'filterTo'      => date('Y-m-1',$this->currentDate)
            ],
            [
                'filterField'   => 'date',
                'filterAs'      => '<=',
                'filterTo'      => date('Y-m-t',$this->currentDate)
            ],
            ];
        $this->items = Auth::user()->transactionsJournals($filter);
        return view('livewire.transactions.list');
    }

    public function store()
    {
        $this->validate([
            'form.date'                 => 'required',
            'form.subcategory_id'       => 'required',
            'form.budget'                 => 'required',
            'form.statementDueDay'      => 'exclude_unless:form.role,creditCard|required',
            'form.statementClosingDay'  => 'exclude_unless:form.role,creditCard|required'
        ]);
        
        $this->form['user_id'] = Auth::user()->id;
        if (!is_int($this->form['statementClosingDay']))
            $this->form['statementClosingDay'] = null;
        if (!is_int($this->form['statementDueDay']))
            $this->form['statementDueDay'] = null;
        if (!is_int($this->form['bank_id']))
            $this->form['bank_id'] = null;
        $this->modelClass::updateOrCreate(['id' => $this->itemID], $this->form);
  
        session()->flash('message', 
            $this->itemID ? __($this->itemClassName.' updated successfully.') : __($this->itemClassName.' created Successfully.'));
  
        $this->closeModal();
        $this->resetInputFields();
    }

    /*
    public function edit($id)
    {
        $item = $this->modelClass::findOrFail($id);
        $this->itemID = $id;
        foreach ($this->form as $key => $value) {
            $this->form[$key] = $item[$key];
        }
    
        $this->openModal();
    }

    public function delete($id)
    {
        $this->modelClass::find($id)->delete();
        session()->flash('message', $this->itemClassName.' deleted successfully.');
    }
    */
}
