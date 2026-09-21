<?php

namespace App\Policies;

use App\Models\MaintenanceRequest as MR;
use App\Models\MaintenanceAssignment;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Auth\Access\Response;

class MaintenanceRequestPolicy
{
    use HandlesAuthorization;

    public function before(User $user, string $ability)
    {
        // 'rate' is excluded from admin bypass so that the full rate() policy
        // always runs — this ensures the Rate button is hidden even for admins
        // once a job has already been rated (1:1 enforcement in the UI).
        if ($ability === 'rate') {
            return null;
        }

        if ($this->isAdminTeam($user)) {
            return Response::allow();
        }
    }


    protected function isAdminTeam(User $user): bool
    {
        // admin/supervisor เป็นผู้ดูแล
        return $user->isAdmin() || $user->isSupervisor();
    }

    protected function isWorker(User $user): bool
    {
        // ทีมงาน (เจ้าหน้าที่/IT/หัวหน้า ฯลฯ ตามระบบคุณ)
        return in_array($user->role, User::teamRoles(), true);
    }

    protected function isAssignedWorker(User $user, MR $req): bool
    {
        if (!$this->isWorker($user)) return false;

        // lead / technician_id (คนรับผิดชอบหลัก)
        if ((int) $req->technician_id === (int) $user->id) return true;

        // in assignments (not cancelled)
        return $req->assignments()
            ->where('user_id', $user->id)
            ->where('status', '!=', MaintenanceAssignment::STATUS_CANCELLED)
            ->exists();
    }

    protected function isOpenForAcknowledge(MR $req, ?User $user = null): bool
    {
        if ($req->status !== MR::STATUS_PENDING) return false;

        // nobody owns it yet — or it is already this worker's (accept / reject below say the same)
        return empty($req->technician_id) || ($user !== null && (int) $req->technician_id === (int) $user->id);
    }

    protected function isOpenForAccept(User $user, MR $req): bool
    {
        if ($req->status !== MR::STATUS_ACKNOWLEDGED) return false;

        return empty($req->technician_id) || (int) $req->technician_id === (int) $user->id;
    }

    protected function isOpenForReject(User $user, MR $req): bool
    {
        if (!in_array($req->status, [MR::STATUS_PENDING, MR::STATUS_ACKNOWLEDGED], true)) return false;

        return empty($req->technician_id) || (int) $req->technician_id === (int) $user->id;
    }

    // view
    public function view(User $user, MR $req): Response
    {
        if ($this->isAdminTeam($user)) return Response::allow();

        // เจ้าหน้าที่ดูได้กว้างขึ้น (ดูได้เมื่ออยู่ในคิวรอรับทราบ หรือ รอคนมาตอบรับ)
        if ($this->isWorker($user) && ($this->isOpenForAcknowledge($req, $user) || $this->isOpenForAccept($user, $req))) return Response::allow();

        // ผู้ที่ถูกมอบหมาย/รับผิดชอบ
        if ($this->isAssignedWorker($user, $req)) return Response::allow();

        // ผู้แจ้ง
        if ((int) $req->reporter_id === (int) $user->id) return Response::allow();

        return Response::deny('อนุญาตให้ดูเฉพาะงานของตนเองหรือที่ได้รับมอบหมายเท่านั้น');
    }

    // update
    public function update(User $user, MR $req): Response
    {
        if ($this->isAdminTeam($user)) return Response::allow();

        // ล็อคการแก้ไขหากจบงานไปแล้ว
        if (in_array((string) $req->status, [MR::STATUS_RESOLVED, MR::STATUS_CLOSED, MR::STATUS_CANCELLED, MR::STATUS_REJECTED], true)) {
            return Response::deny('ใบงานนี้สิ้นสุดแล้ว ไม่สามารถแก้ไขข้อมูลได้');
        }

        // เจ้าหน้าที่ที่อยู่ในงานแก้ไขได้ระหว่างทำงาน
        if ($this->isAssignedWorker($user, $req) && in_array((string) $req->status, [MR::STATUS_ACCEPTED, MR::STATUS_IN_PROGRESS, MR::STATUS_ON_HOLD], true)) {
            return Response::allow();
        }

        // ผู้แจ้งแก้ได้เฉพาะตอนยัง pending / acknowledged และยังไม่มีผู้รับผิดชอบ
        if (
            (int) $req->reporter_id === (int) $user->id &&
            empty($req->technician_id) &&
            in_array((string) $req->status, [MR::STATUS_PENDING, MR::STATUS_ACKNOWLEDGED], true)
        ) {
            return Response::allow();
        }

        return Response::deny('ไม่มีสิทธิ์แก้ไขข้อมูลใบงานนี้');
    }

    // transition (เปลี่ยนสถานะ)
    public function transition(User $user, MR $req): Response
    {
        if ($this->isAdminTeam($user)) return Response::allow();

        // เจ้าหน้าที่ที่ถูก assign ในงานนี้เปลี่ยนสถานะได้
        if ($this->isAssignedWorker($user, $req)) return Response::allow();

        return Response::deny('อนุญาตให้เปลี่ยนสถานะเฉพาะผู้รับผิดชอบงานนี้หรือผู้ดูแลระบบเท่านั้น');
    }

    /**
     * moveTo — may $user move $req to $target? Exactly what the button for that step asks, so the generic transition
     * endpoint and `PUT … status` cannot do what the buttons refuse: a technician closing a job on the reporter's behalf,
     * a job jumping from in_progress to closed, a reporter cancelling a pending request. Whether the step exists at all is
     * still the transition map's call (409).
     */
    public function moveTo(User $user, MR $req, string $target): Response
    {
        $ability = match ($target) {
            MR::STATUS_ACKNOWLEDGED => 'acknowledge',
            MR::STATUS_ACCEPTED     => 'accept',
            MR::STATUS_IN_PROGRESS  => $req->status === MR::STATUS_ON_HOLD ? 'resume' : 'startWork',
            MR::STATUS_ON_HOLD      => 'hold',
            MR::STATUS_RESOLVED     => 'resolve',
            MR::STATUS_CLOSED       => 'close',
            MR::STATUS_CANCELLED    => 'cancel',
            MR::STATUS_REJECTED     => 'reject',
            default                 => null,
        };

        // `pending` and the legacy `completed` are reachable from no status: the transition map refuses them for everybody
        return $ability === null ? Response::allow() : $this->{$ability}($user, $req);
    }

    // acknowledge
    public function acknowledge(User $user, MR $req): Response
    {
        if ($this->isAdminTeam($user)) return Response::allow();

        if (!$this->isWorker($user)) return Response::deny('เฉพาะเจ้าหน้าที่เท่านั้น');

        if ($this->isOpenForAcknowledge($req, $user)) return Response::allow();

        return Response::deny('งานนี้ไม่อยู่ในสถานะที่รับทราบได้');
    }

    // accept
    public function accept(User $user, MR $req): Response
    {
        if ($this->isAdminTeam($user)) return Response::allow();

        if (!$this->isWorker($user)) return Response::deny('เฉพาะเจ้าหน้าที่เท่านั้น');

        if ($this->isOpenForAccept($user, $req)) return Response::allow();

        return Response::deny('งานนี้ถูกมอบหมายแล้วหรือไม่อยู่ในสถานะที่รับเรื่องได้');
    }

    // reject
    public function reject(User $user, MR $req): Response
    {
        if ($this->isAdminTeam($user)) return Response::allow();

        if (!$this->isWorker($user)) return Response::deny('เฉพาะเจ้าหน้าที่เท่านั้น');

        if ($this->isOpenForReject($user, $req)) return Response::allow();

        return Response::deny('งานนี้ไม่อยู่ในสถานะที่ไม่รับเรื่องได้');
    }

    // startWork
    public function startWork(User $user, MR $req): Response
    {
        if ($this->isAdminTeam($user)) return Response::allow();

        // ต้องเป็นทีมงานช่าง/เจ้าหน้าที่
        if (!$this->isWorker($user)) return Response::deny('เฉพาะเจ้าหน้าที่เท่านั้น');

        // ปลดล็อค: เจ้าหน้าที่ทุกคนสามารถกด "เริ่มดำเนินการ" ได้เลย 
        // โดยไม่ต้องถูกสั่งงานก่อน (เพื่อรองรับการจ่ายงานกันเอง)
        if ($req->status === MR::STATUS_ACCEPTED) return Response::allow();

        return Response::deny('ต้องอยู่สถานะรับเรื่องแล้วเท่านั้น');
    }

    // hold
    public function hold(User $user, MR $req): Response
    {
        if ($this->isAdminTeam($user)) return Response::allow();

        if (!$this->isWorker($user)) return Response::deny('เฉพาะเจ้าหน้าที่เท่านั้น');

        if (!$this->isAssignedWorker($user, $req)) {
            return Response::deny('อนุญาตให้หยุดการซ่อมบำรุงชั่วคราวเฉพาะผู้ที่ได้รับมอบหมายเท่านั้น');
        }

        if (in_array($req->status, [MR::STATUS_ACCEPTED, MR::STATUS_IN_PROGRESS], true)) {
            return Response::allow();
        }

        return Response::deny('หยุดการซ่อมบำรุงชั่วคราวได้เมื่อรับเรื่องแล้วหรือกำลังดำเนินการเท่านั้น');
    }

    // resume
    public function resume(User $user, MR $req): Response
    {
        if ($this->isAdminTeam($user)) return Response::allow();

        if (!$this->isWorker($user)) return Response::deny('เฉพาะเจ้าหน้าที่เท่านั้น');

        if (!$this->isAssignedWorker($user, $req)) {
            return Response::deny('อนุญาตให้ดำเนินการต่อเฉพาะผู้ที่ได้รับมอบหมายเท่านั้น');
        }

        if ($req->status === MR::STATUS_ON_HOLD) return Response::allow();

        return Response::deny('ต้องอยู่สถานะพักไว้ก่อนเท่านั้น');
    }

    // resolve
    public function resolve(User $user, MR $req): Response
    {
        if ($this->isAdminTeam($user)) return Response::allow();

        if (!$this->isWorker($user)) return Response::deny('เฉพาะเจ้าหน้าที่เท่านั้น');

        if (!$this->isAssignedWorker($user, $req)) {
            return Response::deny('อนุญาตให้ปิดซ่อมเฉพาะผู้ที่ได้รับมอบหมายเท่านั้น');
        }

        if (in_array($req->status, [MR::STATUS_IN_PROGRESS, MR::STATUS_ON_HOLD], true)) {
            return Response::allow();
        }

        return Response::deny('ต้องอยู่สถานะกำลังดำเนินการหรือพักไว้เท่านั้น');
    }

    // close (ผู้แจ้งยืนยันปิดงาน + admin/supervisor)
    public function close(User $user, MR $req): Response
    {
        if ($this->isAdminTeam($user)) return Response::allow();

        if ($req->status !== MR::STATUS_RESOLVED) {
            return Response::deny('ปิดงานได้เมื่อซ่อมเสร็จแล้วเท่านั้น');
        }

        // check reporter ก่อน worker เพื่อรองรับกรณีเป็นทั้งคู่
        if ((int) $req->reporter_id === (int) $user->id) {
            return Response::allow();
        }

        if ($this->isWorker($user)) {
            return Response::deny('เจ้าหน้าที่ไม่สามารถปิดงานแทนผู้แจ้งได้ ต้องให้ผู้แจ้งยืนยันการปิดงาน');
        }

        return Response::deny('อนุญาตให้ปิดงานเฉพาะผู้แจ้งหรือผู้ดูแลระบบเท่านั้น');
    }

    // attach
    public function attach(User $user, MR $req): Response
    {
        if ($this->isAdminTeam($user)) return Response::allow();

        // ล็อคการแนบไฟล์หากจบงานไปแล้ว
        if (in_array((string) $req->status, [MR::STATUS_RESOLVED, MR::STATUS_CLOSED, MR::STATUS_CANCELLED, MR::STATUS_REJECTED], true)) {
            return Response::deny('ใบงานนี้สิ้นสุดแล้ว ไม่สามารถแนบไฟล์เพิ่มได้');
        }

        // เจ้าหน้าที่แนบไฟล์ได้ตอนทำงาน (รวมถึงตอนเพิ่งรับเรือง หรือตอนซ่อมเสร็จแล้วแต่ยังไม่ปิดงาน)
        if ($this->isAssignedWorker($user, $req) && in_array((string) $req->status, [MR::STATUS_ACKNOWLEDGED, MR::STATUS_ACCEPTED, MR::STATUS_IN_PROGRESS, MR::STATUS_ON_HOLD, MR::STATUS_RESOLVED], true)) {
            return Response::allow();
        }

        // ผู้แจ้งแนบไฟล์ได้จนกว่าเจ้าหน้าที่จะเริ่มงาน
        if ((int) $req->reporter_id === (int) $user->id && in_array((string) $req->status, [MR::STATUS_PENDING, MR::STATUS_ACKNOWLEDGED, MR::STATUS_ACCEPTED], true)) {
            return Response::allow();
        }

        return Response::deny('ไม่มีสิทธิ์แนบไฟล์ในงานนี้ หรือสถานะไม่อนุญาต');
    }

    // deleteAttachment
    public function deleteAttachment(User $user, MR $req): Response
    {
        if ($this->isAdminTeam($user)) return Response::allow();

        // ล็อคการลบไฟล์หากจบงานไปแล้ว
        if (in_array((string) $req->status, [MR::STATUS_RESOLVED, MR::STATUS_CLOSED, MR::STATUS_CANCELLED, MR::STATUS_REJECTED], true)) {
            return Response::deny('ใบงานนี้สิ้นสุดแล้ว ไม่สามารถลบไฟล์ได้');
        }

        // เจ้าหน้าที่ลบไฟล์ได้ตอนทำงาน
        if ($this->isAssignedWorker($user, $req) && in_array((string) $req->status, [MR::STATUS_ACKNOWLEDGED, MR::STATUS_ACCEPTED, MR::STATUS_IN_PROGRESS, MR::STATUS_ON_HOLD, MR::STATUS_RESOLVED], true)) {
            return Response::allow();
        }

        // ผู้แจ้งลบไฟล์ได้ตอนแรกๆ
        if ((int) $req->reporter_id === (int) $user->id && in_array((string) $req->status, [MR::STATUS_PENDING, MR::STATUS_ACKNOWLEDGED, MR::STATUS_ACCEPTED], true)) {
            return Response::allow();
        }

        return Response::deny('ไม่มีสิทธิ์ลบไฟล์แนบ หรือสถานะไม่อนุญาต');
    }

    // assign (มอบหมายทีม)
    public function assign(User $user, MR $req): Response
    {
        // 1. Admin/Supervisor หรือทีมงาน (เจ้าหน้าที่/IT ฯลฯ) สามารถมอบหมายงานได้
        // เพื่อรองรับการส่งต่องาน (Handoff) เช่น IT support รับเรื่องแล้วส่งต่อให้ Programmer
        if ($this->isAdminTeam($user) || $this->isWorker($user)) {
            return Response::allow();
        }

        return Response::deny('อนุญาตให้มอบหมายทีมเจ้าหน้าที่เฉพาะผู้ดูแลหรือทีมเจ้าหน้าที่เท่านั้น');
    }

    // cancel (รวมศูนย์การยกเลิก)
    public function cancel(User $user, MR $req): Response
    {
        // 1. ตรวจสอบสถานะก่อน (เงื่อนไขบังคับ: ยกเลิกได้เฉพาะช่วงที่รับเรื่องหรือกำลังทำอยู่)
        if (!in_array($req->status, [MR::STATUS_ACCEPTED, MR::STATUS_IN_PROGRESS, MR::STATUS_ON_HOLD], true)) {
            return Response::deny('ยกเลิกได้เฉพาะงานที่รับเรื่องแล้วหรือกำลังดำเนินการเท่านั้น');
        }

        // 2. เช็คสิทธิ์รายกลุ่ม
        if ($this->isAdminTeam($user)) return Response::allow();
        if ($this->cancelByReporter($user, $req)->allowed()) return Response::allow();
        if ($this->cancelByTech($user, $req)->allowed()) return Response::allow();

        return Response::deny('คุณไม่มีสิทธิ์ยกเลิกใบงานซ่อมนี้');
    }

    // cancelByReporter
    public function cancelByReporter(User $user, MR $req): Response
    {
        if ((int) $req->reporter_id !== (int) $user->id && !$this->isAdminTeam($user)) {
            return Response::deny('ไม่มีสิทธิ์ยกเลิกคำขอนี้');
        }

        // อนุญาตให้ยกเลิกเฉพาะเมื่อรับเรื่องไปแล้ว หรือเริ่มดำเนินการไปแล้ว ตาม Workflow ที่ผู้ใช้ระบุ
        if (in_array($req->status, [MR::STATUS_ACCEPTED, MR::STATUS_IN_PROGRESS, MR::STATUS_ON_HOLD], true)) {
            return Response::allow();
        }

        return Response::deny('สถานะนี้ไม่สามารถยกเลิกได้');
    }

    // cancelByTech
    public function cancelByTech(User $user, MR $req): Response
    {
        if ($this->isAdminTeam($user)) return Response::allow();

        if (!$this->isWorker($user)) return Response::deny('เฉพาะเจ้าหน้าที่เท่านั้น');

        if (!$this->isAssignedWorker($user, $req)) {
            return Response::deny('อนุญาตให้คืนงานเข้าคิวเฉพาะงานที่ได้รับมอบหมายเท่านั้น');
        }

        if (in_array($req->status, [MR::STATUS_RESOLVED, MR::STATUS_CLOSED, MR::STATUS_CANCELLED], true)) {
            return Response::deny('งานนี้ไม่อยู่ในสถานะที่คืนงานเข้าคิวได้');
        }

        if (in_array($req->status, [MR::STATUS_ACCEPTED, MR::STATUS_IN_PROGRESS, MR::STATUS_ON_HOLD], true)) {
            return Response::allow();
        }

        return Response::deny('สถานะนี้ไม่สามารถคืนงานเข้าคิวได้');
    }

    // setType
    public function setType(User $user, MR $req): Response
    {
        if ($this->isAdminTeam($user)) return Response::allow();

        if ($this->isWorker($user)) return Response::allow();

        return Response::deny('อนุญาตให้เปลี่ยนประเภทงานเฉพาะผู้ดูแลระบบ/เจ้าหน้าที่เท่านั้น');
    }

    // updateOperationLog (แก้ไขรายงานการปฏิบัติงาน)
    public function updateOperationLog(User $user, MR $req): Response
    {
        // 1. Admin/Supervisor แก้ได้ตลอดเวลา
        if ($this->isAdminTeam($user)) return Response::allow();

        // 2. ถ้าปิดงานไปแล้ว (Closed) ห้ามทุกคนยกเว้น Admin แก้ไข
        if ($req->status === MR::STATUS_CLOSED) {
            return Response::deny('ใบงานนี้ปิดงานเรียบร้อยแล้ว ไม่สามารถแก้ไขรายงานการปฏิบัติงานได้');
        }

        // 3. สถานะอื่นๆ (รวมถึง Resolved) เจ้าหน้าที่ที่ได้รับมอบหมายสามารถบันทึก/แก้ไขได้
        if ($this->isAssignedWorker($user, $req)) {
            return Response::allow();
        }

        return Response::deny('ไม่มีสิทธิ์แก้ไขรายงานการปฏิบัติงานในใบงานนี้');
    }

    // ประเมินงานซ่อม (rate)
    public function rate(User $user, MR $req): Response
    {
        // 1. ผู้ประเมินต้องเป็น "คนแจ้งซ่อม" เท่านั้น
        if ((int) $req->reporter_id !== (int) $user->id) {
            return Response::deny('คุณไม่มีสิทธิ์ประเมิน เนื่องจากไม่ได้เป็นผู้แจ้งงานซ่อมนี้');
        }

        // 2. งานต้องอยู่ในสถานะ "ปิดงาน (closed)" เท่านั้น
        if ($req->status !== MR::STATUS_CLOSED) {
            return Response::deny('สามารถประเมินได้เมื่ออนุมัติปิดงานเรียบร้อยแล้วเท่านั้น');
        }

        // 3. ตรวจว่าเคยให้คะแนนแล้วหรือยัง (ป้องกันปุ่มแสดงซ้ำหลังให้คะแนน)
        $alreadyRated = \App\Models\MaintenanceRating::query()
            ->where('maintenance_request_id', $req->id)
            ->exists();

        if ($alreadyRated) {
            return Response::deny('งานนี้มีการให้คะแนนไปแล้ว');
        }

        // 4. อนุญาตให้ประเมินได้
        return Response::allow();
    }
}
