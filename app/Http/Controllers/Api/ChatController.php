<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ChatThread;
use App\Events\ChatMessageDeleted;
use App\Events\ChatThreadLockChanged;
use App\Models\ChatModerationLog;
use App\Support\ChatQuota;
use App\Support\SafeBroadcast;
use App\Models\ChatMessage;
use App\Traits\HandlesChatReads;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Support\Like;

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
                $qq->where('title', 'like', Like::contains($q));
            })
            ->when($r->query('scope') === 'mine' && $userId, fn ($qq) => $qq->inMyList((int) $userId))   // only threads I started or wrote in
            ->when($r->query('scope') === 'hidden' && $userId, fn ($qq) => $qq->hiddenBy((int) $userId))   // the ones I hid from that
            ->orderByDesc('created_at')
            ->paginate(15); // เอา named argument ออกให้ compatible

        $readsMap = $userId
            ? $this->readPointers((int) $userId, $threads->getCollection()->pluck('id')->all())
            : [];
        $hiddenIds = $userId ? $this->hiddenThreadIds((int) $userId, $threads->getCollection()->pluck('id')->all()) : [];

        $payload = [
            'data' => $threads->getCollection()->map(function (ChatThread $th) use ($readsMap, $hiddenIds) {
                $total  = $th->messages_count ?? 0;
                $unread = $this->unreadCount((int) $th->id, $readsMap[$th->id] ?? null, (int) $total);

                return [
                    'id'              => $th->id,
                    'title'           => $th->title,
                    'is_locked'       => (bool) $th->is_locked,
                    'hidden_by_me'    => in_array((int) $th->id, $hiddenIds, true),   // hidden from my "กระทู้ที่มีส่วนร่วม"
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
                'thread_quota' => $r->user() ? ChatQuota::for($r->user()) : null,   // how many threads I may still start today
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

        if (! ChatQuota::canStart($r->user())) {
            return response()->json([
                'message' => ChatQuota::refusal(),
                'code' => 'chat_thread_daily_limit',
                'quota' => ChatQuota::for($r->user()),
            ], 429, ['Retry-After' => (string) ChatQuota::secondsUntilReset()]);
        }

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
            abort(403, 'กระทู้นี้ถูกล็อก ไม่สามารถส่งข้อความได้');
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
            $this->markThreadRead((int) $msg->user_id, (int) $thread->id, (int) $msg->id, reappear: true);
        }

        // Real-time fan-out, same as the web path.
        SafeBroadcast::send(new \App\Events\ChatMessageSent($msg));

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

    public function lock(Request $request, ChatThread $thread)
    {
        $this->authorizeLocking($thread);

        $thread->is_locked = true;
        $thread->save();

        ChatModerationLog::record(ChatModerationLog::LOCK, $request->user(), $thread, request: $request);

        SafeBroadcast::send(new ChatThreadLockChanged((int) $thread->id, true));

        return response()->json([
            'id'         => $thread->id,
            'title'      => $thread->title,
            'is_locked'  => (bool) $thread->is_locked,
            'created_at' => $thread->created_at ? $thread->created_at->toISOString() : null,
        ]);
    }

    public function unlock(Request $request, ChatThread $thread)
    {
        $this->authorizeLocking($thread);

        $thread->is_locked = false;
        $thread->save();

        ChatModerationLog::record(ChatModerationLog::UNLOCK, $request->user(), $thread, request: $request);

        SafeBroadcast::send(new ChatThreadLockChanged((int) $thread->id, false));

        return response()->json([
            'id'         => $thread->id,
            'title'      => $thread->title,
            'is_locked'  => (bool) $thread->is_locked,
            'created_at' => $thread->created_at ? $thread->created_at->toISOString() : null,
        ]);
    }

    /** Delete one message (its author while the thread is open, or a moderator): the same rules and record as the page's. */
    public function destroyMessage(Request $request, ChatThread $thread, ChatMessage $message)
    {
        abort_unless((int) $message->chat_thread_id === (int) $thread->id, 404);
        abort_unless($thread->canDeleteMessage($message, $request->user()), 403, 'เฉพาะเจ้าของข้อความและผู้ดูแลเท่านั้นที่ลบข้อความได้');

        ChatThread::withoutTouching(fn () => $message->delete());

        ChatModerationLog::record(ChatModerationLog::DELETE_MESSAGE, $request->user(), $thread, $message, [
            'message_author_id' => (int) $message->user_id,
            'own' => (int) $message->user_id === (int) $request->user()->id,
        ], $request);

        SafeBroadcast::send(new ChatMessageDeleted((int) $thread->id, (int) $message->id));

        return response()->json(['deleted' => true, 'id' => $message->id]);
    }

    // Hide from / show again in "กระทู้ที่มีส่วนร่วม" — per person, nothing is deleted (same rules as the web path).
    public function hide(Request $r, ChatThread $thread)
    {
        $userId = (int) $r->user()->id;

        if (! ChatThread::involving($userId)->whereKey($thread->id)->exists()) {
            return response()->json(['hidden' => false, 'message' => 'คุณยังไม่ได้มีส่วนร่วมในกระทู้นี้ จึงไม่มีอะไรให้ซ่อน'], 422);
        }

        $this->hideThread($userId, (int) $thread->id);

        return response()->json(['hidden' => true, 'message' => 'ซ่อนกระทู้นี้จากกระทู้ที่มีส่วนร่วมแล้ว']);
    }

    public function unhide(Request $r, ChatThread $thread)
    {
        $this->showThreadAgain((int) $r->user()->id, (int) $thread->id);

        return response()->json(['hidden' => false, 'message' => 'แสดงกระทู้นี้ในกระทู้ที่มีส่วนร่วมอีกครั้งแล้ว']);
    }

    protected function authorizeLocking(ChatThread $thread)
    {
        $this->assertCanLock($thread);
    }

    public function myUpdates(Request $r)
    {
        $user = $r->user();

        if (! $user) {
            return response()->json([]);
        }

        // เอาเฉพาะกระทู้ที่ "เราเกี่ยวข้อง" (เป็นคนตั้ง หรือเคยคอมเมนต์)
        $threads = ChatThread::query()
            ->forTheWidget((int) $user->id)
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
