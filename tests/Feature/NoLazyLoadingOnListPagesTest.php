<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The list pages used to run one extra query per row (`assignments` on My Jobs, `department` on the request list,
 * `type` on the SLA ticket table). Lazy loading is switched off for the run, so any new N+1 on these pages fails here.
 */
class NoLazyLoadingOnListPagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_list_pages_load_their_relations_up_front(): void
    {
        $this->seed();

        $violations = [];
        $page = '';
        Model::preventLazyLoading();
        Model::handleLazyLoadingViolationUsing(function ($model, $relation) use (&$violations, &$page) {
            $violations[] = "$page: ".class_basename($model).'::'.$relation;
        });

        $pages = [
            'my jobs' => route('repairs.my_jobs'),
            'request list' => route('maintenance.requests.index'),
            'sla dashboard' => route('maintenance.sla.index'),
            'asset list' => route('assets.index'),
            'dashboard' => route('repair.dashboard'),
        ];

        foreach (['admin', 'it_support'] as $role) {
            $this->actingAs(User::where('role', $role)->firstOrFail());
            foreach ($pages as $name => $url) {
                $page = "$role $name";
                $this->get($url)->assertOk();
            }
        }

        Model::preventLazyLoading(false);

        $this->assertSame([], array_values(array_unique($violations)));
    }
}
