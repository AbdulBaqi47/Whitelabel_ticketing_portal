<?php

namespace App\Repositories\Organization;

use App\Interfaces\Organization\AgencyRepositoryInterface;

class AgencyRepository implements AgencyRepositoryInterface
{
    public function __construct(protected \App\Models\Organization $model) {}

    public function getAll(?string $term, $per_page)
    {
        $per_page = $per_page ?: config('app.per_page');

        return $this->model::query()
            ->with(['org_wallet', 'parent', 'main_user', 'org_setting'])
            ->select('organizations.*')
            ->join('users', function ($join) {
                $join->on('users.org_id', '=', 'organizations.id')
                    ->where('users.id', function ($subQuery) {
                        $subQuery->select('id')
                            ->from('users')
                            ->whereColumn('org_id', 'organizations.id')
                            ->orderBy('created_at')
                            ->limit(1);
                    });
            })
            ->when($term, function ($query, $term) {
                $query->where(function ($q) use ($term) {
                    $q->whereAny(['organizations.name', 'organizations.address', 'organizations.city'], 'like', "%$term%")
                        ->orWhereHas('main_user', function ($subQuery) use ($term) {
                            $subQuery->whereAny(['email', 'phone_number'], 'like', "%$term%");
                        });
                        
                        // ->orWhereHas('parent', function ($subQuery) use ($term) {
                        //     $subQuery->whereAny(['name'], 'like', "%$term%");
                        // });
                });
            })
            ->where(function ($query) {
                if (in_array(role_name(), ['branch', 'b-employee'])) {
                    $query->where('parent_id', \Illuminate\Support\Facades\Auth::user()->org_id);
                } elseif (request()->filled('branch_id')) {
                    $query->where('parent_id', request('branch_id'));
                }
            })
            ->whereHas('main_user.roles', function ($subQuery) {
                $subQuery->where('name', 'agency');
            })
            ->orderBy('users.last_login', 'desc')
            ->paginate($per_page);
    }

    public function store(array $data)
    {
        $org = $this->model::create([
            'name' => $data['name'],
            'address' =>  $data['address'],
            'city' => $data['city'],
            'parent_id' => $data['org_id']
        ]);

        $user = \App\Models\User::create([
            'name' => $data['name'],
            'org_id' => $org->id,
            'phone_number' => $data['phone_number'],
            'password' => \Illuminate\Support\Facades\Hash::make('admin123'),
            'email' => strtolower($data['email'])
        ]);

        \App\Models\UserSetting::create([
            'org_id' => $org->id,
            'can_issue_ticket' => true,
            'void_charges' => 1000
        ]);

        \App\Models\Wallet::create([
            'uuid' => (string) \Illuminate\Support\Str::uuid(),
            'org_id' => $org->id,
            'per_credit_limit' => $data['per_credit_limit'],
            'available_per_credit_limit' => $data['per_credit_limit'],
        ]);

        \App\Models\Account::create([
            'name' => 'AGENCY_WALLET',
            'code' => \Illuminate\Support\Str::upper(\Illuminate\Support\Str::random(10)),
            'description' => 'AGENCY WALLET ACCOUNT',
            'org_id' => $org->id,
        ]);

        \App\Models\Account::create([
            'name' => 'AGENT_TEMP_LIMIT',
            'code' => \Illuminate\Support\Str::upper(\Illuminate\Support\Str::random(10)),
            'description' => 'AGENCY TEMPORARY LIMIT ACCOUNT',
            'org_id' => $org->id,
        ]);

        $role = \App\Models\Role::where(['name' => 'agency'])->firstOrFail();

        $permissions = \App\Models\Permission::whereJsonContains('preference', 'agency')->where('by_default', true)->pluck('uuid')->toArray();

        $user->assignRole($role);

        $user->syncPermissions($permissions);

        \Illuminate\Support\Facades\Notification::send($user, new \App\Notifications\UserSetPasswordLink($user));
        return $user;
    }

    public function statusChange($uuid)
    {
        $agency_org = $this->findById($uuid);
        $agency_org->status = $agency_org->status ? false : true;
        $agency_org->save();
        return true;
    }

    public function financialProfile($uuid)
    {
        $agency_org = $this->findById($uuid);
        $agency_org->financial_profile = $agency_org->financial_profile ? false : true;
        $agency_org->save();
        return true;
    }

    public function findById($uuid)
    {
        return $this->model::where(['uuid' => $uuid])->whereHas('main_user.roles', function ($subQuery) {
            $subQuery->where('name', 'agency');
        })->firstOrFail();
    }

    public function delete($uuid)
    {
        $org_agency = $this->findById($uuid);
        $org_agency->org_wallet->delete();
        $org_agency->main_user->delete();
        $org_agency->delete();
        return true;
    }


    public function update(array $data, int|string $uuid)
    {
        $org_agency = $this->findById($uuid);

        if($org_agency->parent_id != $data['org_id']){
            \App\Models\MarignOrigination::where('org_id', $org_agency->id)->delete();
        }

        $org_agency->update([
            'name' => $data['name'],
            'address' =>  $data['address'],
            'city' => $data['city'],
            'parent_id' => $data['org_id']
        ]);
        $user = \App\Models\User::whereHas('roles', function ($subQuery) {
            $subQuery->where('name', 'agency');
        })->where('org_id', $org_agency->id)->firstOrFail();
        $user->name = $data['name'];
        $user->email = $data['email'];
        $user->phone_number = $data['phone_number'];
        $user->save();
        $org_agency->org_wallet()->update(['per_credit_limit' => $data['per_credit_limit']]);
        return $org_agency;
    }

    public function dropDown()
    {
        return $this->model::query()->whereHas('main_user.roles', function ($subQuery) {
            $subQuery->where('name', 'agency');
        })->with('parent')->get()->map(function ($item) {
            return [
                'id' => $item->id,
                'name' => $item->name,
                'branch_id' => $item->parent->id
            ];
        });
    }
}
