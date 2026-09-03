<?php

namespace App\Repositories\BankAccounts;

use App\Models\Bank;
use App\Interfaces\BankAccounts\BankAccountsRepositoryInterface;
use Illuminate\Support\Facades\DB;
class BankAccountsRepository implements BankAccountsRepositoryInterface
{
    public function __construct(protected Bank $model) {}

    public function getAll(?string $term,  $per_page)
    {

        $per_page = $per_page ?: config('app.per_page');
        $query = $this->model::query()
            ->when($term, function ($query, $term) {
                $query->whereAny(['account_number', 'account_holder_name', 'bank_name', 'iban', 'contact_number'], 'like', "%{$term}%");
            })
            ->whereStatus(Bank::ACTIVE);
            if(in_array(role_name(), ['branch', 'b-employee'])){

                $query->addSelect([
                    'bank_visibility' => DB::table('bank_visibility')
                        ->selectRaw('COUNT(*) > 0')
                        ->whereColumn('bank_visibility.bank_id', 'banks.id')
                        ->where('bank_visibility.org_id', org_id())
                ]);

                $query->where(function ($subQuery) {
                    return $subQuery->whereNull('org_id')
                        ->orWhere('org_id',org_id())
                        ->orWhereExists(function ($visibilityQuery) {
                            $visibilityQuery->select(DB::raw(1))
                                ->from('bank_visibility')
                                ->whereColumn('bank_visibility.bank_id', 'banks.id')
                                ->where('bank_visibility.org_id', org_id());
                        });
                });
            }

            if(in_array(role_name(), \App\Models\Role::HEAD_ROLES)){
                $query->whereNull('org_id');
            }
            return $query->paginate($per_page);
    }

    public function store(array $data)
    {
        if(in_array(role_name(), ['branch', 'b-employee'])){
            $data = [...$data, 'org_id' => org_id()];
        }
        return $this->model::create($data);
    }

    public function findById(int|string $uuid)
    {
        return $this->model::where(['uuid' => $uuid])->first();
    }

    public  function update(array $data, int|string $uuid)
    {
        $bank_account = $this->findById($uuid);
        $bank_account->update($data);
        return $bank_account;
    }

    public function delete(int|string $uuid)
    {
        $bank_account = $this->findById($uuid);
        if ($bank_account->deposits->count() > 0) {
            return ['status' => false, 'message' => 'Unable to delete bank account: associated deposits exist.'];
        }
        $bank_account->delete();
        return true;
    }

    public function bankList()
    {
        $query = $this->model::select([
            'account_number',
            'account_holder_name',
            'bank_name',
            'iban',
            'bank_address',
            'contact_number',
            'bank_logo'
        ])->where('status', true);

        if(in_array(role_name(), \App\Models\Role::AGENCY_ROLES)){

            $org = \Illuminate\Support\Facades\Auth::user()->organization;

            $parent_id = is_null($org->parent_id)
                ? $org->id
                : $org->parent_id;

            $query->whereExists(function ($visibilityQuery) use ($parent_id) {
                $visibilityQuery->select(DB::raw(1))
                    ->from('bank_visibility')
                    ->whereColumn('bank_visibility.bank_id', 'banks.id')
                    ->where('bank_visibility.org_id', $parent_id);
            });

        }

        if(in_array(role_name(), ['branch', 'b-employee'])){
            $query->whereNull('org_id')->orWhere('org_id', org_id());     
        }

        return $query->get();
    }

    public function bankDropDown()
    {
        return $this->model::select('id', 'bank_name')->where('status', true)->get();
    }

    public function showToAgencies($bankId)
    {
        $record = DB::table('bank_visibility')
            ->where('bank_id', $bankId)
            ->where('org_id', org_id())
            ->first();

        if ($record) {
            DB::table('bank_visibility')
                ->where('bank_id', $bankId)
                ->where('org_id', org_id())
                ->delete();

            return true;
        }

        DB::table('bank_visibility')->insert([
            'bank_id' => $bankId,
            'org_id'  => org_id()
        ]);

        return true;
    }
}
