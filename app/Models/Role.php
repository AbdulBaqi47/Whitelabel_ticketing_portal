<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Spatie\Permission\Models\Role as SpatieRole;

class Role extends SpatieRole
{
    use HasFactory;
    use HasUuids;
    protected $primaryKey = 'uuid';

    protected $hidden = [
        'pivot'
    ];

    const AGENCY_ROLES = ['agency', 'a-employee', 'sub-agent'];
    const MANAGMENT_ROLES = ['head-office', 'h-employee', 'branch', 'b-employee'];
    const HEAD_ROLES = ['head-office', 'h-employee'];
    const HEADOFFICE = 'head-office';
    const ALL_ROLES = ['head-office', 'branch', 'h-employee',  'b-employee', 'agency', 'a-employee', 'sub-agent'];
}