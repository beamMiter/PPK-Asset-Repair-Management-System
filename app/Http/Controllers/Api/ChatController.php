<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ChatThread;
use App\Models\ChatMessage;
use App\Traits\HandlesChatReads;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ChatController extends Controller
{
    use HandlesChatReads;

    public function index(Request $r)
    {
        $q = (string) $r->query('q', '');
        $userId = optional($r->user())->id;

        $threads = ChatThread::query()
            ->with('author:id,name')
            ->withCount('messages')
            ->with(['latestMessage' => function ($qq) {
                $qq->with('user:id,name');
            }])
            ->when($q !== '', function ($qq) use ($q) {
                $qq->where('title', 'like', "%{$q}%");
            })
            ->orderByDesc('created_at')
            ->paginate(15); // เอา named argument ออกให้ compatible

        $readsMap = $userId
            ? $this->readPointers((int) $userId, $threads->getCollection()->pluck('id')->all())
            : [];

        $payload = [
            'data' => $threads->getCollection()->map(function (ChatThread $th) use ($readsMap) {
                $total  = $th->messages_count ?? 0;
                $unread = $this->unreadCount((int) $th->id, $readsMap[$th->id] ?? null, (int) $total);

                return [
                    'id'              => $th->id,
                    'title'           => $th->title,
                    'is_locked'       => (bool) $th->is_locked,
                    'created_at'      => $th->created_at ? $th->created_at->toISOString() : null,
                    'author'          => $th->author ? [
                        'id'   => $th->author->id,
                        'name' => $th->author->name,
                    ] : null,
                    'messages_count'  => $total,
                    'unread_count'    => $unread,
                    'latest_message'  => $th->latestMessage ? [
                        'id'         => $th->latestMessage->id,
                        'user'       => $th->latestMessage->user ? [
                            'id'   => $th->latestMessage->user->id,
                            'name' => $th->latestMessage->user->name,
                        ] : null,
                        'body'       => $th->latestMessage->body,
                        'created_at' => $th->latestMessage->created_at ? $th->latestMessage->created_at->toISOString() : null,
                    ] : null,
                ];
            }),
            'meta' => [
                'current_page' => $threads->currentPage(),
                'per_page'     => $threads->perPage(),
                'total'        => $threads->total(),
                'last_page'    => $threads->lastPage(),
            ],
        ];

        return response()->json($payload);
    }

    public function store(Request $r)
    {
        $data = $r->validate([
            'title' => ['required', 'string', 'max:180'],
        ]);

        $thread = ChatThread::create([
            'title'     => $data['title'],
            'author_id' => Auth::id(),
            'is_locked' => false,
        ]);

        return response()->json([
            'id'         => $thread->id,
            'title'      => $thread->title,
            'is_locked'  => (bool) $thread->is_locked,
            'created_at' => $thread->created_at ? $thread->created_at->toISOString() : null,
        ], 201);
    }

    public function show(ChatThread $thread)
    {
        $thread->load('author:id,name');

        $latest = $thread->messages()
            ->with('user:id,name')
            ->latest('created_at')
            ->take(10)
            ->get()
            ->reverse()
            ->values();

        return response()->json([
            'id'         => $thread->id,
            'title'      => $thread->title,
            'is_locked'  => (bool) $thread->is_locked,
            'created_at' => $thread->created_at ? $thread->created_at->toISOString() : null,
            'author'     => $thread->author ? [
                'id'   => $thread->author->id,
                'name' => $thread->author->name,
            ] : null,
            'latest_messages' => $latest->map(function (ChatMessage $m) {
                return [
                    'id'         => $m->id,
                    'user'       => $m->user ? [
                        'id'   => $m->user->id,
                        'name' => $m->user->name,
                    ] : null,
                    'body'       => $m->body,
                    'created_at' => $m->created_at ? $m->created_at->toISOString() : null,
                ];
            }),
        ]);
    }

    public function messages(Request $r, ChatThread $thread)
    {
        $afterId = $r->integer('after_id');
        $limit   = (int) $r->query('limit', 50);
        $limit   = max(1, min($limit, 100));

        $q = $thread->messages()
            ->with('user:id,name');

        if ($afterId) {
            $q->where('id', '>', $afterId)
              ->orderBy('id', 'asc');
        } else {
            $q->orderBy('created_at', 'asc');
        }

        $messages = $q->take($limit)->get();

        return response()->json([
            'data' => $messages->map(function (ChatMessage $m) {
                return [
                    'id'         => $m->id,
                    'user'       => $m->user ? [
                        'id'   => $m->user->id,
                        'name' => $m->user->name,
                    ] : null,
                    'body'       => $m->body,
                    'created_at' => $m->created_at ? $m->created_at->toISOString() : null,
                ];
            }),
        ]);
    }

    public function storeMessage(Request $r, ChatThread $thread)
    {
        // ถ้าล็อกแล้ว ห้ามโพสต์
        if ($thread->is_locked) {
            abort(403, 'Thread locked');
        }

        $data = $r->validate([
            'body' => ['required', 'string', 'max:3000'],
        ]);

        $msg = $thread->messages()->create([
            'user_id' => Auth::id(),
            'body'    => $data['body'],
        ]);

        $msg->load('user:id,name');

        if ($msg->user_id) {
            $this->markThreadRead((int) $msg->user_id, (int) $thread->id, (int) $msg->id);
        }

        // Real-time fan-out, same as the web path.
        broadcast(new \App\Events\ChatMessageSent($msg));

        return response()->json([
            'id'         => $msg->id,
            'user'       => $msg->user ? [
                'id'   => $msg->user->id,
                'name' => $msg->user->name,
            ] : null,
            'body'       => $msg->body,
            'created_at' => $msg->created_at ? $msg->created_at->toISOString() : null,
        ], 201);
    }

    public function lock(ChatThread $thread)
    {
        $this->authorizeLocking($thread);

        $thread->is_locked = true;
        $thread->save();

        return response()->json([
            'id'         => $thread->id,
            'title'      => $thread->title,
            'is_locked'  => (bool) $thread->is_locked,
            'created_at' => $thread->created_at ? $thread->created_at->toISOString() : null,
        ]);
    }

    public function unlock(ChatThread $thread)
    {
        $this->authorizeLocking($thread);

        $thread->is_locked = false;
        $thread->save();

        return response()->json([
            'id'         => $thread->id,
            'title'      => $thread->title,
            'is_locked'  => (bool) $thread->is_locked,
            'created_at' => $thread->created_at ? $thread->created_at->toISOString() : null,
        ]);
    }

    protected function authorizeLocking(ChatThread $thread)
    {
        $this->assertCanManageThread();
    }

    public function myUpdates(Request $r)
    {
        $user = $r->user();

        if (! $user) {
            return response()->json([]);
        }

        // เอาเฉพาะกระทู้ที่ "เราเกี่ยวข้อง" (เป็นคนตั้ง หรือเคยคอมเมนต์)
        $threads = ChatThread::query()
            ->where(function ($q) use ($user) {
                $q->where('author_id', $user->id)
                  ->orWhereHas('messages', function ($mm) use ($user) {
                      $mm->where('user_id', $user->id);
                  });
            })
            ->with(['latestMessage.user'])
            ->withCount('messages')
            ->latest('updated_at')
            ->take(30)
            ->get();

        if ($threads->isEmpty()) {
            return response()->json([]);
        }

        // map last_read_message_id ของ user นี้ เพื่อคำนวณ unread
        $readsMap = $this->readPointers((int) $user->id, $threads->pluck('id')->all());

        $items = $threads->map(function (ChatThread $th) use ($readsMap) {
            $total  = $th->messages_count ?? 0;
            $unread = $this->unreadCount((int) $th->id, $readsMap[$th->id] ?? null, (int) $total);

            $latest = $th->latestMessage;

            return [
                'id'              => $th->id,
                'title'           => $th->title ?? ('กระทู้ #' . $th->id),
                'show_url'        => route('chat.show', $th->id), // web route
                'unread'          => $unread,
                'last_user_name'  => $latest && $latest->user ? $latest->user->name : null,
                'last_body'       => $latest ? $latest->body : null,
                'last_created_at' => $latest && $latest->created_at
                    ? $latest->created_at->toISOString()
                    : null,
            ];
        })->values();

        return response()->json($items);
    }
}
