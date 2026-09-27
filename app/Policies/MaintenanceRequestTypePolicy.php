<?php

namespace App\Policies;

use App\Models\MaintenanceRequestType;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Auth\Access\Response;

class MaintenanceRequestTypePolicy
{
    use HandlesAuthorization;

    protected function canManage(User $user): bool
    {
        return $user->isAdmin();
    }

    public function viewAny(User $user): Response
    {
        return $this->canManage($user)
            ? Response::allow()
            : Response::deny('ไม่มีสิทธิ์เข้าหน้าตั้งค่าประเภทงาน');
    }

    public function view(User $user, MaintenanceRequestType $type): Response
    {
        return $this->viewAny($user);
    }

    public function create(User $user): Response
    {
        return $this->canManage($user)
            ? Response::allow()
            : Response::deny('ไม่มีสิทธิ์เพิ่มประเภทงาน');
    }

    public function update(User $user, MaintenanceRequestType $type): Response
    {
        return $this->canManage($user)
            ? Response::allow()
            : Response::deny('ไม่มีสิทธิ์แก้ไขประเภทงาน');
    }

    public function delete(User $user, MaintenanceRequestType $type): Response
    {
        return $this->canManage($user)
            ? Response::allow()
            : Response::deny('ไม่มีสิทธิ์ปิดใช้งานประเภทงาน');
    }
}
