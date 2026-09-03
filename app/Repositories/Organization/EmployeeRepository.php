<?php

namespace App\Repositories\Organization;

use App\Interfaces\Organization\EmployeeRepositoryInterface;
use Illuminate\Support\Facades\{Hash, Auth};

class EmployeeRepository implements EmployeeRepositoryInterface
{
    public function __construct(protected \App\Models\User $model) {}

    public function getAll(?string $term, $per_page)
    {

        $per_page = $per_page ?: config('app.per_page');
        return $this->model::query()->whereHas('roles', function($subQuery){
                if(is_head_office()){
                    $subQuery->where('name', 'h-employee');
                }elseif(is_branch()){
                    $subQuery->where('name', 'b-employee')->where('org_id', Auth::user()->org_id);
                }else{
                    $subQuery->where('name', 'a-employee');
                }
            })->orderBy('id', 'desc')->when($term, function ($query, $term){

                request()->merge(['page'=>1]);
                $query->whereAny(['name', 'email', 'phone_number'], 'like', "%$term%");
            })->orderBy('id','desc')->paginate($per_page);
    }

    public function store(array $data)
    {
        $reqData = [
            'email' => strtolower($data['email']),
            'name'=>$data['name'],
            'phone_number'=>$data['phone_number'],
            'org_id' => Auth::user()->org_id,
            'password'=>Hash::make('admin123'),
        ];
        $user = $this->model::create($reqData);
        $role = \App\Models\Role::where(['name' => _employee()])->firstOrFail();
        $permissions = \App\Models\Permission::whereJsonContains('preference', _employee())->where('by_default', true)->pluck('uuid')->toArray();
        $user->assignRole($role);
        $user->syncPermissions($permissions);

        \Illuminate\Support\Facades\Notification::send($user, new \App\Notifications\UserSetPasswordLink($user));
        return $user;
    }

    public function statusChange($uuid)
    {
        $user = $this->findById($uuid);
        $user->status = $user->status ? '0' : '1';
        $user->save();
        return true;
    }

    public function findById($uuid)
    {
        return $this->model::where(['uuid' => $uuid])->firstOrFail();
    }

    public function delete($uuid)
    {
        $user = $this->findById($uuid);
        $user->delete();
        return true;
    }


    public function update(array $data, int|string $uuid)
    {
        $user = $this->findById($uuid);
        $user->update($data);
        return $user;
    }
}
