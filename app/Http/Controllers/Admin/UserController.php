<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Department;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use App\Support\Toast;

class UserController extends Controller
{
    public function __construct()
    {
        // ให้เข้าหน้านี้ได้เฉพาะคนที่ล็อกอินแล้ว
        $this->middleware('auth');
    }

    /**
     * แสดงรายการผู้ใช้ทั้งหมด + filter / search
     */
    public function index(Request $request)
    {
        // list ของ code role ที่มีในระบบ (มาจากตาราง roles ผ่าน User::availableRoles())
        $roleCodes   = User::availableRoles();
        $roleLabels  = User::roleLabels(); // ['admin' => 'ผู้ดูแลระบบ', ...]

        $q = User::query()
            ->with([
                'departmentRef' => function ($qq) {
                    $qq->select([
                        'id',
                        'code',
                        'name_th',
                        'name_en',
                    ]);
                },
                'roleRef' => function ($qq) {
                    $qq->select([
                        'id',
                        'code',
                        'name_th',
                        'name_en',
                    ]);
                },
            ]);

        // ค้นหาจากชื่อ, citizen_id, email, department (ตัวพิมพ์เล็ก-ใหญ่ไม่สน)
        $search = trim((string) $request->get('s', ''));
        if ($search !== '') {
            $needle = mb_strtolower($search);
            $q->where(function ($qq) use ($needle) {
                $qq->whereRaw('LOWER(name) LIKE ?', ["%{$needle}%"])
                   ->orWhereRaw('LOWER(email) LIKE ?', ["%{$needle}%"])
                   ->orWhereRaw('LOWER(citizen_id) LIKE ?', ["%{$needle}%"])
                   ->orWhereRaw('LOWER(COALESCE(department, \'\')) LIKE ?', ["%{$needle}%"]);
            });
        }

        // filter ตาม role
        $role = $request->get('role');
        if ($role !== null && $role !== '') {
            $q->where('role', $role);
        }

        // filter ตาม department (เก็บเป็น code ใน users.department)
        $dep = $request->get('department');
        if ($dep !== null && $dep !== '') {
            $q->where('department', $dep);
        }

        $list = $q
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        // dropdown หน่วยงาน
        $departments = Department::orderBy('code')->get([
            'id',
            'code',
            'name_th',
            'name_en',
        ]);

        return view('admin.users.index', [
            'list'        => $list,
            'roles'       => $roleCodes,
            'roleLabels'  => $roleLabels,
            'filters'     => [
                's'          => $search,
                'role'       => $role,
                'department' => $dep,
            ],
            'departments' => $departments,
        ]);
    }

    /**
     * ฟอร์มสร้างผู้ใช้ใหม่
     */
    public function create()
    {
        $currentUser = Auth::user();
        if ($currentUser->isTechnician() && !$currentUser->isAdmin() && !$currentUser->isSupervisor()) {
            abort(403, 'เฉพาะผู้ดูแลระบบและหัวหน้างานเท่านั้นทื่สามารถสร้างผู้ใช้ได้');
        }

        $roleCodes   = User::availableRoles();
        $roleLabels  = User::roleLabels();

        $departments = Department::orderBy('code')->get([
            'id',
            'code',
            'name_th',
            'name_en',
        ]);

        return view('admin.users.create', [
            'roles'       => $roleCodes,
            'roleLabels'  => $roleLabels,
            'departments' => $departments,
        ]);
    }

    /**
     * บันทึกผู้ใช้ใหม่
     */
    public function store(Request $request)
    {
        $currentUser = Auth::user();
        if ($currentUser->isTechnician() && !$currentUser->isAdmin() && !$currentUser->isSupervisor()) {
            abort(403, 'เฉพาะผู้ดูแลระบบและหัวหน้างานเท่านั้นทื่สามารถสร้างผู้ใช้ได้');
        }

        $availableRoles = User::availableRoles();

        $validator = Validator::make(
            $request->all(),
            [
                'name'        => ['required', 'string', 'max:255'],
                'citizen_id'  => [
                    'required',
                    'digits:13',
                    'unique:users,citizen_id',
                ],
                'email'       => [
                    'nullable',
                    'email',
                    'max:255',
                    'unique:users,email',
                ],
                'password'    => ['required', 'string', 'min:8', 'confirmed'],
                'role'        => [
                    'required',
                    'string',
                    Rule::in($availableRoles),
                ],
                'department'  => [
                    'nullable',
                    'string',
                    'max:255',
                    Rule::exists('departments', 'code'),
                ],
            ],
            [
                'name.required'         => 'กรุณากรอกชื่อผู้ใช้',
                'name.max'              => 'ชื่อผู้ใช้ต้องไม่เกิน :max ตัวอักษร',
                'citizen_id.required'   => 'กรุณากรอกเลขบัตรประชาชน',
                'citizen_id.digits'     => 'เลขบัตรประชาชนต้องมี 13 หลัก',
                'citizen_id.unique'     => 'เลขบัตรประชาชนนี้ถูกใช้ไปแล้ว',
                'email.email'           => 'รูปแบบอีเมลไม่ถูกต้อง',
                'email.max'             => 'อีเมลต้องไม่เกิน :max ตัวอักษร',
                'email.unique'          => 'อีเมลนี้ถูกใช้ไปแล้ว',
                'password.required'     => 'กรุณากรอกรหัสผ่าน',
                'password.min'          => 'รหัสผ่านต้องมีอย่างน้อย :min ตัวอักษร',
                'password.confirmed'    => 'รหัสผ่านและการยืนยันรหัสผ่านไม่ตรงกัน',
                'role.required'         => 'กรุณาเลือกบทบาทผู้ใช้',
                'role.in'               => 'บทบาทที่เลือกไม่ถูกต้อง',
                'department.max'        => 'ชื่อหน่วยงานต้องไม่เกิน :max ตัวอักษร',
                'department.exists'     => 'หน่วยงานที่เลือกไม่ถูกต้อง',
            ]
        );

        if ($validator->fails()) {
            return back()
                ->withInput()
                ->withErrors($validator)
                ->with('toast', Toast::error('บันทึกผู้ใช้ไม่สำเร็จ: ' . $validator->errors()->first(), 3200));
        }

        $data = $validator->validated();

        // เช็คการตั้งค่า Role: เฉพาะ Admin เท่านั้นที่แอด Admin/Supervisor ได้
        if (in_array($data['role'], [User::ROLE_ADMIN, User::ROLE_SUPERVISOR])) {
            if (!Auth::user()->isAdmin()) {
                return back()->withInput()->with('toast', Toast::error('เฉพาะผู้ดูแลระบบเท่านั้นที่สามารถเพิ่มยศแอดมินหรือหัวหน้างานได้', 4000));
            }
        }

        try {
            DB::beginTransaction();

            $user              = new User();
            $user->name        = $data['name'];
            $user->citizen_id  = $data['citizen_id'];
            $user->email       = $data['email'] ?? null;
            $user->password    = Hash::make($data['password']);
            $user->role        = $data['role'];
            $user->department  = $data['department'] ?? null;

            $user->save();

            DB::commit();

            return redirect()
                ->route('admin.users.index')
                ->with('toast', Toast::success('สร้างผู้ใช้ใหม่เรียบร้อยแล้ว', 2800));
        } catch (\Throwable $e) {
            DB::rollBack();
            report($e);

            return back()
                ->withInput()
                ->with('toast', Toast::error('เกิดข้อผิดพลาดระหว่างบันทึกข้อมูลผู้ใช้', 4000));
        }
    }

    /**
     * ฟอร์มแก้ไขผู้ใช้
     */
    public function edit(User $user)
    {
        $currentUser = Auth::user();
        if ($currentUser->isTechnician() && !$currentUser->isAdmin() && !$currentUser->isSupervisor() && $user->id !== $currentUser->id) {
            abort(403, 'เจ้าหน้าที่สามารถแก้ไขได้เฉพาะประวัติส่วนตัวของตนเองเท่านั้น');
        }

        $roleCodes   = User::availableRoles();
        $roleLabels  = User::roleLabels();

        // ป้องกันไม่ให้ใครที่ไม่ได้เป็น Admin มาแก้ไข Admin/Supervisor
        if (in_array($user->role, [User::ROLE_ADMIN, User::ROLE_SUPERVISOR])) {
            if (!Auth::user()->isAdmin() && Auth::id() !== $user->id) {
                return back()->with('toast', Toast::error('คุณไม่มีสิทธิ์แก้ไขข้อมูลของผู้ดูแลระบบหรือหัวหน้างานท่านอื่น', 4000));
            }
        }

        $departments = Department::orderBy('code')->get([
            'id',
            'code',
            'name_th',
            'name_en',
        ]);

        return view('admin.users.edit', [
            'user'        => $user,
            'roles'       => $roleCodes,
            'roleLabels'  => $roleLabels,
            'departments' => $departments,
        ]);
    }

    /**
     * อัพเดตข้อมูลผู้ใช้
     */
    public function update(Request $request, User $user)
    {
        $currentUser = Auth::user();
        if ($currentUser->isTechnician() && !$currentUser->isAdmin() && !$currentUser->isSupervisor() && $user->id !== $currentUser->id) {
            abort(403, 'เจ้าหน้าที่สามารถแก้ไขได้เฉพาะประวัติส่วนตัวของตนเองเท่านั้น');
        }

        $availableRoles = User::availableRoles();

        $validator = Validator::make(
            $request->all(),
            [
                'name'        => ['required', 'string', 'max:255'],
                'citizen_id'  => [
                    'required',
                    'digits:13',
                    Rule::unique('users', 'citizen_id')->ignore($user->id),
                ],
                'email'       => [
                    'nullable',
                    'email',
                    'max:255',
                    Rule::unique('users', 'email')->ignore($user->id),
                ],
                'password'    => ['nullable', 'string', 'min:8', 'confirmed'],
                'role'        => [
                    'required',
                    'string',
                    Rule::in($availableRoles),
                ],
                'department'  => [
                    'nullable',
                    'string',
                    'max:255',
                    Rule::exists('departments', 'code'),
                ],
            ],
            [
                'name.required'         => 'กรุณากรอกชื่อผู้ใช้',
                'name.max'              => 'ชื่อผู้ใช้ต้องไม่เกิน :max ตัวอักษร',
                'citizen_id.required'   => 'กรุณากรอกเลขบัตรประชาชน',
                'citizen_id.digits'     => 'เลขบัตรประชาชนต้องมี 13 หลัก',
                'citizen_id.unique'     => 'เลขบัตรประชาชนนี้ถูกใช้ไปแล้ว',
                'email.email'           => 'รูปแบบอีเมลไม่ถูกต้อง',
                'email.max'             => 'อีเมลต้องไม่เกิน :max ตัวอักษร',
                'email.unique'          => 'อีเมลนี้ถูกใช้ไปแล้ว',
                'password.min'          => 'รหัสผ่านต้องมีอย่างน้อย :min ตัวอักษร',
                'password.confirmed'    => 'รหัสผ่านและการยืนยันรหัสผ่านไม่ตรงกัน',
                'role.required'         => 'กรุณาเลือกบทบาทผู้ใช้',
                'role.in'               => 'บทบาทที่เลือกไม่ถูกต้อง',
                'department.max'        => 'ชื่อหน่วยงานต้องไม่เกิน :max ตัวอักษร',
                'department.exists'     => 'หน่วยงานที่เลือกไม่ถูกต้อง',
            ]
        );

        if ($validator->fails()) {
            return back()
                ->withInput()
                ->withErrors($validator)
                ->with('toast', Toast::error('อัพเดตข้อมูลผู้ใช้ไม่สำเร็จ: ' . $validator->errors()->first(), 3200));
        }

        $data = $validator->validated();

        // ป้องกันสิทธิ์การอัพเดต Role เปลี่ยนผู้ใช้เป็น Admin/Supervisor
        if (in_array($data['role'], [User::ROLE_ADMIN, User::ROLE_SUPERVISOR])) {
            if (!Auth::user()->isAdmin() && !(Auth::id() === $user->id && $user->role === $data['role'])) {
                return back()->withInput()->with('toast', Toast::error('เฉพาะผู้ดูแลระบบเท่านั้นที่สามารถตั้งค่าผู้ใช้เป็นแอดมินหรือหัวหน้างานได้', 4000));
            }
        }

        // ป้องกันการแก้ไข Admin/Supervisor ท่านอื่น ถ้าคนแก้ไม่ใช่ Admin 
        if (in_array($user->role, [User::ROLE_ADMIN, User::ROLE_SUPERVISOR]) && !Auth::user()->isAdmin() && Auth::id() !== $user->id) {
            return back()->withInput()->with('toast', Toast::error('คุณไม่มีสิทธิ์อัพเดตข้อมูลของผู้ดูแลระบบหรือหัวหน้างานท่านอื่น', 4000));
        }

        try {
            DB::beginTransaction();

            $user->name        = $data['name'];
            $user->citizen_id  = $data['citizen_id'];
            $user->email       = $data['email'] ?? null;
            $user->role        = $data['role'];
            $user->department  = $data['department'] ?? null;

            if (!empty($data['password'])) {
                $user->password = Hash::make($data['password']);
            }

            $user->save();

            DB::commit();

            return redirect()
                ->route('admin.users.index')
                ->with('toast', Toast::success('อัพเดตข้อมูลผู้ใช้เรียบร้อยแล้ว', 2800));
        } catch (\Throwable $e) {
            DB::rollBack();
            report($e);

            return back()
                ->withInput()
                ->with('toast', Toast::error('เกิดข้อผิดพลาดระหว่างอัพเดตข้อมูลผู้ใช้', 4000));
        }
    }

    /**
     * ระงับบัญชี — แทนการลบ: ประวัติ (ใบแจ้งซ่อม, แชท, คะแนน, การมอบหมาย) อยู่ครบ
     * แต่เข้าสู่ระบบไม่ได้และถูกมอบหมายงานใหม่ไม่ได้ เปิดใช้งานกลับได้ภายหลัง
     */
    public function suspend(User $user)
    {
        if ($user->id === Auth::id()) {
            return back()->with('toast', Toast::error('ไม่สามารถระงับบัญชีของตัวเองได้', 3200));
        }

        if ($user->isSuspended()) {
            return back()->with('toast', Toast::warning('บัญชีนี้ถูกระงับอยู่แล้ว', 2800));
        }

        $user->forceFill(['suspended_at' => now(), 'remember_token' => null])->save();
        $user->tokens()->delete(); // API tokens stop working at once; web sessions end on their next request

        Log::info('[Admin\UserController::suspend] account suspended', ['user_id' => $user->id, 'actor_id' => Auth::id()]);

        return back()->with('toast', Toast::success("ระงับบัญชี {$user->name} แล้ว", 2800));
    }

    public function reactivate(User $user)
    {
        if (! $user->isSuspended()) {
            return back()->with('toast', Toast::warning('บัญชีนี้ไม่ได้ถูกระงับ', 2800));
        }

        $user->forceFill(['suspended_at' => null])->save();

        Log::info('[Admin\UserController::reactivate] account reactivated', ['user_id' => $user->id, 'actor_id' => Auth::id()]);

        return back()->with('toast', Toast::success("เปิดใช้งานบัญชี {$user->name} แล้ว", 2800));
    }
}
