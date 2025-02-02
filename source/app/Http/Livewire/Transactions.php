<?php

namespace App\Http\Livewire;

use Livewire\Component;
use Illuminate\Support\Facades\Auth;
use App\Models\TransactionsJournal;
use App\Models\Account;
use App\Helpers\Helper;
use App\Models\CreditCard;
use App\Models\Subcategory;
use App\Models\Transaction;
use App\Models\Currency;
use Illuminate\Support\Facades\Route;

class Transactions extends Component
{
    protected $listeners = [
        'selectedAccount' => 'updateAccount',
        'selectedSubcategory' => 'updateSubcategory'
      ];
    public $modelClass = TransactionsJournal::class;
    public $itemClassName = 'Transactions';
    public $assetAccounts;
    public $expenseAccounts;
    public $incomeAccounts;
    public $accountRoles;
    public $transactionTypes;
    public $selected = [];
    public $selectedCC = [];
    public $selectedAll;
    public $currentDate;
    public $cumulativeBalanceLastMonth = 0;
    public $items;
    public $itemID;
    public $accountFilter = '';
    public $accountFilterRole = '';
    public $statementTransactions = [];
    public $accountCreditCards = [];
    public $statementBudgetDate;
    public $form = array(
        'date'                   => '',
        'user_id'                => '',
        'budget_date'            => '',
        'description'            => '',
        'transaction_number'     => '',
        'transactions'           => []
    );
    public $transactionsValidation;
    public $isOpen = 0;
    public $isStatementOpen = 0;

    public $pickCreditCardId;
    public $pickTransactionId;

    public $pickBudgetTransactionId = [];
    public $pickBudgetDate;
    
    public function pickCreditCard($transactionId, $creditCardId = null) {
        $this->pickCreditCardId = $creditCardId;
        $this->pickTransactionId = $transactionId;
    }

    public function selectStatementBudgetDate() {
        $this->showStatement($this->accountFilter);
    }

    public function pickBudgetDate($transactionId, $budgetDate = null) {
        $this->pickBudgetTransactionId = [$transactionId => true];
        $this->pickBudgetDate = date('Y-m',$budgetDate);
    }

    public function openBalance($accountID) {
        if ($accountID != null && $accountID != '') {
            redirect()->to('/transaction/' . $accountID . '/' . date('Y',$this->currentDate) . '/' . date('m',$this->currentDate));
        } else {
            redirect()->to('/transaction/' . date('Y',$this->currentDate) . '/' . date('m',$this->currentDate));
        }
    }

    //Function to receive from AutoComplete component selected Value and update $form Object
    public function updateSubcategory($field) {
        $path = &$this->form;
        $nestedPath = explode("-",$field['wiredTo']);

        foreach ($nestedPath as $value) {
            $path = &$path[$value];
        }
        $path = $field['selectedSubcategory'] != null ? Subcategory::find($field['selectedSubcategory']['id']) : null;
    }
    //Function to receive from AutoComplete component selected Value and update $form Object
    public function updateAccount($field) {
        $nestedPath = explode("-",$field['wiredTo']);
        $this->form[$nestedPath[0]][$nestedPath[1]][$nestedPath[2]] = $field['selectedAccount'] != null ? Account::find($field['selectedAccount']['id']) : null;
        $this->form[$nestedPath[0]][$nestedPath[1]][$nestedPath[2] . "_name"] = $field['query'];

        $path = $this->form[$nestedPath[0]][$nestedPath[1]][$nestedPath[2]];
        $pathName = $this->form[$nestedPath[0]][$nestedPath[1]][$nestedPath[2] . "_name"];
        //Wonderful: as we received user choice, now we've got to validate it, meaning:
        //1) Credit Account can't be equal to Debit Account
        //2) TODO: If Credit Account = Income Acconunt, Debit Account can't be Expense Account or Vice-Versa (At least I guess)
        if ($nestedPath[2] == 'credit_account') {
            if ($this->form['transactions'][$nestedPath[1]]['debit_account'] != null && $path != null &&
                    $this->form['transactions'][$nestedPath[1]]['debit_account']['id'] == $path->id) {
                $this->form['transactions'][$nestedPath[1]]['debit_account'] = null;
                $this->form['transactions'][$nestedPath[1]]['debit_account_name'] = '';
                $this->emit('updatedSelectedAccount', ['nestedPath' => $nestedPath[0] . '-' . $nestedPath[1] . '-debit_account' , 'value' => null]);
            }
        } else {
            if ($this->form['transactions'][$nestedPath[1]]['credit_account'] != null && $path != null &&
                    $this->form['transactions'][$nestedPath[1]]['credit_account']['id'] == $path->id) {
                $this->form['transactions'][$nestedPath[1]]['credit_account'] = null;
                $this->form['transactions'][$nestedPath[1]]['credit_account_name'] = '';
                $this->emit('updatedSelectedAccount', ['nestedPath' => $nestedPath[0] . '-' . $nestedPath[1] . '-credit_account' , 'value' => null]);
            }
        }
    }

    //function to update the checkbox, not really reliable, TODO: study a better way to control this objects using Alpine.JS
    public function updatedSelectedAll($selectedAllValue) {
        foreach ($this->items as $item) {
            $this->selected[$item->id] = $selectedAllValue;
        }
    }
    public function updatedSelected($value, $key) {
        $allSelected = true;
        foreach ($this->selected as $key => $value) {
            if (!$value)
                $allSelected = false;
        }
        $this->selectedAll = $allSelected;
    }

    public function mount($year = null, $month = null, $accountID = null) {
        if ($accountID != null) {
            $this->accountFilter = $accountID;
            $this->accountFilterRole = Account::findOrFail($accountID)->role;
        } else {
            $this->accountFilter = '';
            $this->accountFilterRole = '';
        }
        if ($year != null)
            $this->currentDate  = mktime(0,0,0,$month,1,$year);
        else
            $this->currentDate = time();
        $this->statementBudgetDate = date('Y-m',$this->currentDate);
        $this->transactionsValidation = '';
        $this->accountRoles = config('dearbudget.accountRoles');
        $this->transactionTypes = config('dearbudget.transactionTypes');
        $this->assetAccounts = Account::where('role','checkingAccount')
        ->orWhere('role','walletCash')
        ->orWhere('role','investmentAccount')
        ->orWhere('role','creditCard')
        ->get()->toJSON();
        $this->expenseAccounts = Account::where('role','expenseAccount')->get()->toJSON();
        $this->incomeAccounts = Account::where('role','incomeAccount')->get()->toJSON();

        $filter = [
            [
                'filterField'   => 'deleted_at',
                'filterAs'      => '=',
                'filterTo'      => null
            ],
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
        $this->items = Auth::user()->transactionsJournals($filter, $accountID);
        if ($accountID != null && $accountID != '') {
            $filterLastMonth = [
                [
                    'filterField'   => 'deleted_at',
                    'filterAs'      => '=',
                    'filterTo'      => null
                ],
                [
                    'filterField'   => 'date',
                    'filterAs'      => '<',
                    'filterTo'      => date('Y-m-01',$this->currentDate)
                ],
                ];
            $itensUpToLastMonth = Auth::user()->transactionsJournals($filterLastMonth, $accountID);
            $this->cumulativeBalanceLastMonth = 0;
            foreach ($itensUpToLastMonth as $item) {
                foreach ($item->transactions as $transaction) {
                    if ($transaction->creditAccount != null && $transaction->creditAccount->id == $accountID) {
                        $this->cumulativeBalanceLastMonth -= $transaction->amount;
                    } elseif ($transaction->debitAccount != null && $transaction->debitAccount->id == $accountID) {
                        $this->cumulativeBalanceLastMonth += $transaction->amount;
                    }
                }
            }
        } 
        foreach ($this->items as $item) {
            $this->selected[$item->id] = false;
        }
        $this->selectedAll = false;
    }

    public function render()
    {
        return view('livewire.transactions.list');
    }

    public function store()
    {
        $this->validate([
            'form.date'                             => 'required',
            'form.description'                      => 'required',
            'form.transactions'                     => 'required|array|min:1'
        ]);

        $validated = true;
        //ok we validated transaction journal, let's validate each transaction now:
        foreach ($this->form['transactions'] as $key => $value) {
            if (($value['credit_account'] == null && $value['credit_account_name'] != null)
                || ($value['credit_account'] != null && $value['credit_account']['role'] == 'incomeAccount')
            ) {
                if (($value['debit_account'] == null && $value['debit_account_name'] != null)
                    || ($value['debit_account'] != null && $value['debit_account']['role'] == 'expenseAccount')) {
                        $validated = false;
                        $this->transactionsValidation = __('You can\'t have a transaction that transfers money from an income account to an expense account');
                }

            }
            if ($value['credit_account'] != null && $value['debit_account'] != null && $value['debit_account'] == $value['credit_account']) {
                $validated = false;
                $this->transactionsValidation = __('You can\'t have a transaction that transfers money from and to the same account');
            }
            if ($value['credit_account'] == null && $value['credit_account_name'] == null) {
                $validated = false;
                $this->transactionsValidation = __('You have to define a credit account for all transactions');
            }
            if ($value['debit_account'] == null && $value['debit_account_name'] == null) {
                $validated = false;
                $this->transactionsValidation = __('You have to define a debit account for all transactions');
            }
        }

        if ($validated) {
            $this->form['user_id'] = Auth::user()->id;
            if ($this->form['budget_date'] == '')
                $this->form['budget_date'] = null;
            $transactionJournal = $this->modelClass::updateOrCreate(['id' => $this->itemID], $this->form);

            //Ok, we created or updated the Transaction Journal... now we have to create the transactions:
            foreach ($transactionJournal->transactions as $transaction) {
                //first we gonna find each transactions that have been deleted or updated:
                $delete = true;
                foreach ($this->form['transactions'] as $updatedTransaction) {
                    if ($updatedTransaction['id'] == $transaction->id) {
                        $delete = false;
                    }
                }
                if ($delete) {
                    Transaction::find($transaction->id)->delete();
                }
            }
            //Now we have to create the new transactions:
            foreach ($this->form['transactions'] as $updatedTransaction) {
                if ($updatedTransaction['credit_account'] == null && $updatedTransaction['credit_account_name'] != null) {
                    $currencies = Currency::where('active', 1)->orderBy('default', 'DESC')->get();
                    $account = Account::create([
                        'name'       => $updatedTransaction['credit_account_name'],
                        'role'       => 'incomeAccount',
                        'curreny_id' => $currencies[0]->id,
                        'user_id'    => Auth::user()->id
                    ]);

                    //it doesn't exist yet:
                    $subTransactionJournal = TransactionsJournal::create([
                        'user_id'   => Auth::user()->id,
                        'date' => $this->form['date'],
                        'description' => __('Opening Balance')
                    ]);
                    Transaction::create([
                        'debit_account_id' => $account->id,
                        'type' => array_search('initialBalance',array_column($this->transactionTypes,'type')),
                        'transactions_journal_id' => $subTransactionJournal->id,
                        'amount' => 0
                    ]);
                    $updatedTransaction['credit_account'] = $account;
                }
                if ($updatedTransaction['debit_account'] == null && $updatedTransaction['debit_account_name'] != null) {
                    $currencies = Currency::where('active', 1)->orderBy('default', 'DESC')->get();
                    $account = Account::create([
                        'name'       => $updatedTransaction['debit_account_name'],
                        'role'       => 'expenseAccount',
                        'curreny_id' => $currencies[0]->id,
                        'user_id'    => Auth::user()->id
                    ]);

                    //it doesn't exist yet:
                    $subTransactionJournal = TransactionsJournal::create([
                        'user_id'   => Auth::user()->id,
                        'date' => $this->form['date'],
                        'description' => __('Opening Balance')
                    ]);
                    Transaction::create([
                        'debit_account_id' => $account->id,
                        'type' => array_search('initialBalance',array_column($this->transactionTypes,'type')),
                        'transactions_journal_id' => $subTransactionJournal->id,
                        'amount' => 0
                    ]);
                    $updatedTransaction['debit_account'] = $account;
                }
                $updatedTransaction['transactions_journal_id'] = $transactionJournal->id;

                if ($updatedTransaction['credit_account']['role'] == 'incomeAccount') {
                    $updatedTransaction['type'] = array_search('income',array_column($this->transactionTypes,'type'));
                } elseif ($updatedTransaction['debit_account']['role'] == 'expenseAccount') {
                    $updatedTransaction['type'] = array_search('expense',array_column($this->transactionTypes,'type'));
                } else {
                    $updatedTransaction['type'] = array_search('transfer',array_column($this->transactionTypes,'type'));
                }
                $updatedTransaction['credit_account_id'] = $updatedTransaction['credit_account']['id'];
                $updatedTransaction['debit_account_id'] = $updatedTransaction['debit_account']['id'];
                if ($updatedTransaction['subcategory'] != null) {
                    $updatedTransaction['subcategory_id'] = $updatedTransaction['subcategory']['id'];
                }
                Transaction::updateOrCreate(['id' => $updatedTransaction['id'] ?? null], $updatedTransaction);
            }

            session()->flash('message',
                $this->itemID ? __($this->itemClassName.' updated successfully.') : __($this->itemClassName.' created successfully.'));
            $this->mount(date('Y',$this->currentDate), date('m',$this->currentDate), $this->accountFilter);
            $this->resetInputFields();
            $this->closeModal();
            
        }
    }

    public function closeModal() {
        $this->isOpen = false;
        $this->isStatementOpen = false;
    }

    public function updateCreditCard() {
        $transaction = Transaction::findOrFail($this->pickTransactionId);
        if ($transaction->transactionsJournal->user->id != Auth::user()->id) {
            die('Unauthorized');
        }
        $transaction->credit_card_id = ($this->pickCreditCardId == '' ? null : $this->pickCreditCardId);
        $transaction->save();
        $this->pickTransactionId = null;
        $this->showStatement($this->accountFilter);
    }

    public function updateBudgetDate() {
        foreach ($this->pickBudgetTransactionId as $key => $value) {
            if ($value) {
                $transaction = Transaction::findOrFail($key)->transactionsJournal;
                if ($transaction->user->id != Auth::user()->id) {
                    die('Unauthorized');
                }
                $transaction->budget_date = ($this->pickBudgetDate == '' ? null : $this->pickBudgetDate . '-01');
                $transaction->save();
                
            }
        }
        $this->pickBudgetTransactionId = [];
        $this->showStatement($this->accountFilter);
    }

    public function showStatement($accountID) {
        $this->accountCreditCards = CreditCard::where('account_id','=',$accountID)->get()->toArray();
        array_unshift($this->accountCreditCards, ['id' => '','name'=> __('No Card'), 'total' => 0]);
        
        $this->statementTransactions = Transaction::where(function($query0) use ($accountID) {
            $query0->where('credit_account_id','=',$accountID)->orWhere('debit_account_id','=',$accountID);
            })
            ->whereHas('transactionsJournal', function($q) {
            $q->where(function($query1) {
                $query1->where('budget_date', '<=',date('Y-m-t',strtotime($this->statementBudgetDate.'-01')))
                    ->where('budget_date','>=',date('Y-m-01',strtotime($this->statementBudgetDate.'-01')));
                })
                ->orWhere(function($query2) {
                    $query2->where('budget_date','=',null)
                    ->where('date', '<=',date('Y-m-t',strtotime($this->statementBudgetDate.'-01')))
                    ->where('date','>=',date('Y-m-01',strtotime($this->statementBudgetDate.'-01')));
                });
            })
            ->with(['transactionsJournal'])->with('subcategory')->with('creditAccount')->with('debitAccount')
            ->get()->sortBy('transactionsJournal.date')->toArray();
            usort($this->statementTransactions, function ($a, $b) {
                return strtotime($a['transactions_journal']['date']) - strtotime($b['transactions_journal']['date']);
            });
        foreach ($this->accountCreditCards as $ccKey => $creditCard) {
            foreach ($this->statementTransactions as $item) {
                $this->selectedCC[$item['id']] = false;
                if (($item['credit_card_id']) == ($creditCard['id'])) {
                    $this->accountCreditCards[$ccKey]['total'] =
                        ($this->accountCreditCards[$ccKey]['total'] ?? 0) 
                            + ($item['amount'] * ($item['credit_account_id'] == $accountID ? 1 : 0));
                }
            }
        }
        $this->isStatementOpen = true;
    }

    public function new() {
        $this->resetInputFields();
        $this->isOpen = true;
    }

    private function resetInputFields() {
        foreach ($this->form as $key => $value) {
            if (is_array($this->form[$key])) {
                if ($key == 'transactions') {
                    $this->form[$key] = [];
                    $this->form[$key] = [[
                        'credit_account'         => null,
                        'debit_account'          => null,
                        'credit_account_name'    => null,
                        'debit_account_name'    => null,
                        'transactions_journal_id'   => '',
                        'amount'                    => 0,
                        'subcategory'            => ''
                    ]];
                    $this->emit('updatedSelectedAccount', ['nestedPath'  => 'transactions-0-debit_account' , 'value' => null]);
                    $this->emit('updatedSelectedAccount', ['nestedPath'  => 'transactions-0-credit_account' , 'value' => null]);
                    $this->emit('updatedSelectedCategory', ['nestedPath' => 'transactions-0-subcategory' , 'value' => null]);
                } else {
                    $this->form[$key] = [];
                }
            } else {
                $this->form[$key] = '';
            }
        }

    }

    public function edit($id) {
        $this->transactionsValidation = '';
        $item = $this->modelClass::findOrFail($id);
        $this->itemID = $id;
        foreach ($this->form as $key => $value) {
            if ($key == 'transactions') {
                $transactions = [];
                foreach ($item[$key] as $transaction) {
                    array_push($transactions, [
                        'id'    => $transaction->id,
                        'credit_account' => $transaction->creditAccount,
                        'credit_account_name' => $transaction->creditAccount->name,
                        'debit_account' => $transaction->debitAccount,
                        'debit_account_name' => $transaction->debitAccount->name,
                        'amount' => $transaction->amount,
                        'subcategory' => $transaction->subcategory,
                    ]);
                }
                $this->form[$key] = $transactions;
            } else {
                $this->form[$key] = $item[$key];
            }
        }
        $this->isOpen = true;
        $this->emit('editTransaction', $transactionJournalId = $id);
    }

    public function addTransaction() {
        array_push($this->form['transactions'],
            [
                'credit_account'         => null,
                'debit_account'          => null,
                'credit_account_name'    => '',
                'debit_account_name'     => '',
                'transactions_journal_id'   => '',
                'amount'                    => 0,
                'subcategory'            => ''
            ]
        );
    }

    public function deleteTransaction($id) {
        unset($this->form['transactions'][$id]);
    }

    public function delete($id) {
        $this->modelClass::find($id)->delete();
        session()->flash('message', $this->itemClassName.' deleted successfully.');
    }

    public function changeSelectedStatementDate() {
        foreach ($this->selectedCC as $key => $value) {
            if ($value) {
                $this->pickBudgetTransactionId[$key] = $value;
            }
        }
    }

    public function deleteSelected() {
        foreach ($this->items as $value) {
            if ($this->selected[$value['id']]) {
                $allowed = true;
                foreach ($value['transactions'] as $transaction) {
                    if ($transaction['type'] == array_search('initialBalance',array_column($this->transactionTypes,'type'))) {
                        $allowed = false;
                    }
                }
                if ($allowed) {
                    $this->delete($value['id']);
                }
            }
        }
    }
}
