<?php

namespace App\Http\Livewire;

use Livewire\Component;
use Illuminate\Support\Facades\Auth;
use App\Models\Bank;
use App\Models\Currency;
use App\Models\Account;

class Accounts extends Component
{
    public $modelClass = Account::Class;
    public $itemClassName = 'Account';
    public $accountFilter = 'assetLiabilityAccount';
    public $accountRoles;
    public $banks;
    public $currencies;
    public $items;
    public $itemID;
    public $form = array(
        'name'                  => '',
        'description'           => '',
        'currency_id'           => '',
        'number'                => '',
        'role'                  => '',
        'bank_id'               => '',
        'user_id'               => '',
        'statementClosingDay'   => null,
        'statementDueDay'       => null,
    );
    public $isOpen = 0;

    public function render()
    {
        $this->currencies = Currency::where('active', 1)->orderBy('default', 'DESC')->get();
        $this->accountRoles = config('dearbudget.accountRoles');
        $this->banks = Bank::all();
        
        if ($this->accountFilter == 'assetLiabilityAccount')
            {
                $this->items = Auth::user()->accounts
                    ->whereIn('role',['checkingAccount','creditCard','walletCash','investmentAccount']);
            }
        elseif ($this->accountFilter == 'assetAccount')
            {
                $this->items = Auth::user()->accounts->whereIn('role',['checkingAccount','walletCash','investmentAccount']);
            }
        elseif ($this->accountFilter == 'liabilityAccount')
            {
                $this->items = Auth::user()->accounts->where('role','=','creditCard');
            }
        elseif ($this->accountFilter == 'expenseAccount')
            {
                $this->items = Auth::user()->accounts->where('role','=','expenseAccount');
            }
        else 
            {
                $this->items = Auth::user()->accounts->where('role','=','incomeAccount');
            }
        return view('livewire.accounts.list');
    }

    public function create()
    {
        $this->resetInputFields();
        $this->openModal();
        $this->itemID = ''; 
        $this->form['currency_id'] = $this->currencies[0]->id;
        $this->form['role'] = array_keys($this->accountRoles)[0];
    }
  
    public function openModal()
    {
        $this->isOpen = true;
    }

    public function closeModal()
    {
        $this->isOpen = false;
    }
  
    private function resetInputFields(){
        foreach ($this->form as $key => $value) {
            $this->form[$key] = '';
        }
    }

    /**
     * Get the error messages for the defined validation rules.
     *
     * @return array
     */
    public function messages()
    {
        return [
            'form.statementDueDay.required' => 'Due Day required for Credit Card',
            'form.statementClosingDay.required' => 'Closing Day required for Credit Card',
            'form.name.required' => 'Name required',
            'form.number.required' => 'Account Number required',
            'form.currency_id.required' => 'Currency required',
            'form.role,required' => 'Account role required'
        ];
    }

    public function store()
    {
        $this->validate([
            'form.name'                 => 'required',
            'form.currency_id'          => 'required',
            'form.role'                 => 'required',
            'form.statementDueDay'      => 'exclude_unless:form.role,creditCard|required',
            'form.statementClosingDay'  => 'exclude_unless:form.role,creditCard|required'
        ]);
        
        $this->form['user_id'] = Auth::user()->id;
        
        if (!is_numeric($this->form['statementClosingDay']))
            $this->form['statementClosingDay'] = null;
        if (!is_numeric($this->form['statementDueDay']))
            $this->form['statementDueDay'] = null;
        if (!is_numeric($this->form['bank_id']))
            $this->form['bank_id'] = null;
        $this->modelClass::updateOrCreate(['id' => $this->itemID], $this->form);
  
        session()->flash('message', 
            $this->itemID ? __($this->itemClassName.' updated successfully.') : __($this->itemClassName.' created Successfully.'));
  
        $this->closeModal();
        $this->resetInputFields();
    }

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
}
