<?php

namespace App\Repositories\Organization;

use App\Interfaces\Organization\AgentManagementRepositoryInterface;
use Illuminate\Support\Facades\{Hash, Auth};
class AgentManagementRepository implements AgentManagementRepositoryInterface
{
    public function __construct(protected \App\Models\Organization $model) {}

    public function getAll(?string $term, $per_page)
    {
        $per_page = $per_page ?: config('app.per_page');
        return \App\Models\User::select(['name','id','email', 'status', 'uuid'])->when($term, function($query,$term){
            $query->whereAny(['name', 'email'], 'like', "%{$term}%");
        })->with('roles:name')->where(function($query){
            $query->whereHas('roles', function ($subQuery) {
                if(request()->filled('agent_type')){
                    $subQuery->where('name', request()->agent_type);
                }else{
                    $subQuery->whereIn('name', ['a-employee', 'sub-agent']);
                }
            });
        })->where('org_id', Auth::user()->org_id)->paginate($per_page);
    }

    public function store(array $data)
    {
        $user = \App\Models\User::create([
            'name' => $data['name'],
            'email' => strtolower($data['email']),
            'org_id' => Auth::user()->org_id,
            'password' => \Illuminate\Support\Facades\Hash::make('admin123')
        ]);

        $role = \App\Models\Role::where(['name' => $data['agent_type']])->firstOrFail();
        $permissions = \App\Models\Permission::whereJsonContains('preference', $data['agent_type'])->where('by_default', true)->pluck('uuid')->toArray();
        $user->assignRole($role);
        $user->syncPermissions($permissions);
        \Illuminate\Support\Facades\Notification::send($user, new \App\Notifications\UserSetPasswordLink($user));
        return $user;
    }

    public function statusChange($uuid)
    {
        $agent = $this->findById($uuid);
        $agent->status = $agent->status ? false : true;
        $agent->save();
        return $agent;
    }

    public function findById($uuid)
    {
        return \App\Models\User::where(['uuid' => $uuid])->firstOrFail();
    }

    public function delete($uuid)
    {
        $org_agency = $this->findById($uuid);
        $org_agency->delete();
        return true;
    }


    public function update(array $data, int|string $uuid)
    {
        $agent = $this->findById($uuid);
        $agent->update([
            'name' => $data['name'],
            'email'=> strtolower($data['email'])
        ]);

        if (!empty($data['agent_type'])) {
            $agent->syncRoles([$data['agent_type']]);
        }

        return $agent;
    }
}
