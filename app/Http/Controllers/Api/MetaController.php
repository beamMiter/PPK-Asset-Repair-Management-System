<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Http\Request;
use App\Models\User;
use App\Support\Like;

class MetaController extends Controller
{
    public function departments(Request $r)
    {
        $q = trim((string) $r->query('q'));
        $builder = DB::table('departments')
            ->select('id','code','name_th','name_en')
            ->when($q, function ($qq) use ($q) {
                $qq->where(function($w) use ($q){
                    $w->where('code','like',Like::contains($q))
                      ->orWhere('name_th','like',Like::contains($q))
                      ->orWhere('name_en','like',Like::contains($q));
                });
            })
            ->orderBy('name_th');

        $rows = $builder->limit(50)->get()->map(function($r){
            $display = trim($r->name_th).' '.($r->name_en ? "({$r->name_en})" : '');
            return [
                'id'         => $r->id,
                'code'       => $r->code,
                'name'       => trim($r->name_th), // primary name
                'display'    => trim($display),
            ];
        });
        return response()->json(['data' => $rows]);
    }

    public function categories(Request $r)
    {
        $q = trim((string) $r->query('q'));
        $builder = DB::table('asset_categories')
            ->select('id','name','slug','color','description')
            ->when($q, function ($qq) use ($q) {
                $qq->where('name','like',Like::contains($q))
                   ->orWhere('slug','like',Like::contains($q));
            })
            ->orderBy('name');

        $rows = $builder->limit(50)->get()->map(function($r){
            return [
                'id'          => $r->id,
                'name'        => $r->name,
                'slug'        => $r->slug,
                'color'       => $r->color,
                'description' => $r->description,
            ];
        });
        return response()->json(['data' => $rows]);
    }

    public function users(Request $r)
    {
        $role = $r->query('role');
        $q = User::query()->active()->select('id','name','role','department');
        if ($role) {
            $q->where('role', $role);
        }
        // A plain member is offered the staff they deal with, not a directory of every other member's name and department.
        if (! Gate::allows('maintenance-type-manage')) {
            $q->whereIn('role', User::teamRoles());
        }
        $rows = $q->orderBy('name')->limit(200)->get()->map(fn(User $u) => [
            'id'         => $u->id,
            'name'       => $u->name,
            'role'       => $u->role,
            'department' => $u->department,
        ]);
        return response()->json(['data' => $rows]);
    }
}
