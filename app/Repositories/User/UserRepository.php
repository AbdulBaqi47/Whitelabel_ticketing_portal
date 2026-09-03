<?php

namespace App\Repositories\User;

use App\Models\User;
use App\Interfaces\Auth\UserRepositoryInterface;
use Illuminate\Support\Facades\Hash;

class UserRepository implements UserRepositoryInterface
{
    public function __construct(protected User $model)
    {
    }

    // public function findByEmail(string $email)
    // {
    //     return $this->model->where('email', $email)->first();
    // }

    // public function create(array $data)
    // {
    //     $data['password'] = Hash::make($data['password']);
    //     return $this->model->create($data);
    // }

    // public function update(int $id, array $data)
    // {
    //     $user = $this->findById($id);
    //     if (isset($data['password'])) {
    //         $data['password'] = Hash::make($data['password']);
    //     }
    //     $user->update($data);
    //     return $user;
    // }

    // public function delete(int $id)
    // {
    //     return $this->findById($id)->delete();
    // }

    // public function findById(int $id)
    // {
    //     return $this->model->findOrFail($id);
    // }

    // public function verifyEmail(int $id)
    // {
    //     $user = $this->findById($id);
    //     if (!$user->hasVerifiedEmail()) {
    //         $user->markEmailAsVerified();
    //     }
    //     return $user;
    // }

    // public function markEmailAsVerified(int $id)
    // {
    //     $user = $this->findById($id);
    //     $user->markEmailAsVerified();
    //     return $user;
    // }

    // public function updatePassword(int $id, string $password)
    // {
    //     return $this->update($id, [
    //         'password' => $password
    //     ]);
    // }
}