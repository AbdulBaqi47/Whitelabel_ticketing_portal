<?php

namespace App\Repositories\Organization;

use App\Interfaces\Organization\BranchRepositoryInterface;
use Illuminate\Support\Facades\Hash;

class BranchRepository implements BranchRepositoryInterface
{
    public function __construct(protected \App\Models\Organization $model) {}

    public function getAll(?string $term, $per_page)
    {
        $per_page = $per_page ?: config('app.per_page');

        return $this->model::query()
            ->with('main_user','org_setting:org_id,can_issue_ticket')
            ->orderBy('id', 'desc')
            ->when($term, function ($query, $term) {
                $query->where(function ($q) use ($term) {
                    $q->whereAny(['name', 'address', 'city'], 'like', "%$term%")
                    ->orWhereHas('main_user', function ($subQuery) use ($term) {
                        $subQuery->whereAny(['name','email', 'phone_number'], 'like', "%$term%");
                    });
                });
            })
            ->whereHas('main_user.roles', function ($subQuery) {
                $subQuery->where('name', 'branch');
            })
            ->orgFilter()
            ->paginate($per_page);

    }

    public function store(array $data)
    {
        $org_data = [
            'name' => $data['org_name'],
            'address' =>  $data['address'],
            'city' => $data['city'],
        ];
        $org = $this->model::create($org_data);

        $user = \App\Models\User::create([
            'name' => $data['manager_name'],
            'org_id' => $org->id,
            'phone_number' => $data['phone_number'],
            'password' => Hash::make('admin123'),
            'email' => strtolower($data['email'])
        ]);
        $role = \App\Models\Role::where(['name' => 'branch'])->firstOrFail();
        $permissions = \App\Models\Permission::whereJsonContains('preference', 'branch')->where('by_default', true)->pluck('uuid')->toArray();
        $user->assignRole($role);
        $user->syncPermissions($permissions);
        \Illuminate\Support\Facades\Notification::send($user, new \App\Notifications\UserSetPasswordLink($user));
        return $user;
    }

    public function statusChange($uuid)
    {
        $branch = $this->findById($uuid);
        $branch->status = $branch->status ? false : true;
        $branch->save();
        return true;
    }

    public function showAllBookings($uuid){
        $branch = $this->findById($uuid);
        $branch->show_all_bookings = $branch->show_all_bookings ? false : true;
        $branch->save();
        return true;
    }

    public function findById($uuid)
    {
        return $this->model::where(['uuid' => $uuid])->firstOrFail();
    }

    public function delete($uuid)
    {
        $branch = $this->model::where('uuid', $uuid)->first();
        $branch->main_user->delete();
        $branch->delete();
        return true;
    }

    public function update(array $data, int|string $uuid)
    {
        $branch = $this->model::where('uuid', $uuid)->first();
        $branch->update([
            'name' => $data['org_name'],
            'address' =>  $data['address'],
            'city' => $data['city'],
        ]);

        $branch->main_user->update([
            'name' => $data['manager_name'],
            'phone_number' => $data['phone_number'],
            'email' => strtolower($data['email'])
        ]);

        return $branch;
    }

    public function dropDown()
    {
        return $this->model::whereHas('main_user.roles', function ($subQuery) {
            $subQuery->where('name', 'branch');
        })->select(['id', 'name'])->get();
    }

    public function dropDownByType(array $request)
    {

        $type = $request['type'] == 'agencies' ? 'agency' : 'b-employee';
        if ($request['type'] === 'agencies') {


            return $this->model::whereHas('main_user.roles', function ($subQuery) use ($type) {
                $subQuery->where('name', $type);
            })->where('parent_id', $request['branchId'])->get(['id', 'name']);
        } else {

            return \App\Models\User::where('org_id',  $request['branchId'])->whereHas('roles', function ($subQuery) use ($type) {
                $subQuery->where('name', $type);
            })->get(['id', 'name']);
        }
    }
}
