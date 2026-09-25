<?php

namespace App\Http\Controllers;

use App\Models\ChatThread;
use App\Support\SafeBroadcast;
use App\Traits\HandlesChatReads;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ChatController extends Controller
{
    use HandlesChatReads;

    public function index(Request $r)
    {
        $q = (string) $r->string('q');
        $scope = $r->query('scope') === 'mine' ? 'mine' : 'all';   // 'mine': only the threads I started or wrote in
        $meId = (int) Auth::id();

        $threads = ChatThread::query()
            ->with('author:id,name')
            ->withCount('messages')
            ->with(['latestMessage' => fn($qq) => $qq->with('user:id,name')])
            ->when($scope === 'mine', fn($qq) => $qq->inMyList($meId))
            ->when($q, fn($qq) => $qq->where('title', 'like', "%{$q}%"))
            ->orderByDesc('created_at')
            ->paginate(15)
            ->withQueryString();

        $activeThreadId = $r->integer('thread_id');
        $activeThread = null;
        $messages = collect();
        $totalMessages = 0;
        $lastAt = null;

        if ($activeThreadId) {
            $activeThread = ChatThread::find($activeThreadId);
            if ($activeThread) {
                $messages = $activeThread->messages()
                    ->with('user:id,name')
                    ->latest('created_at')
                    ->take(50)
                    ->get()
                    ->reverse()
                    ->values();

                $totalMessages = $activeThread->messages()->count();
                $lastAt = $messages->last()?->created_at ?? $activeThread->created_at;

                // Opening a thread reads it. Without this, only posting a message ever advanced the pointer
                // (storeMessage below), so the widget's "ใหม่" badge for a thread never cleared just by looking at it.
                if ($lastMessageId = $activeThread->messages()->max('id')) {
                    $this->markThreadRead((int) Auth::id(), (int) $activeThread->id, (int) $lastMessageId);
                }
            }
        }

        $me = Auth::user();
        $canManageLock = $me && $me->role !== 'member';

        // the open thread and me: can I hide it from my list (I took part, and have not), or bring it back (I hid it)?
        $hiddenByMe = $activeThread ? $this->hasHiddenThread($meId, (int) $activeThread->id) : false;
        $canHide = $activeThread && ! $hiddenByMe && ChatThread::involving($meId)->whereKey($activeThread->id)->exists();

        // what each list holds, for the two tabs (not narrowed by the search)
        $counts = ['all' => ChatThread::count(), 'mine' => ChatThread::inMyList($meId)->count()];

        return view('chat.index', compact('threads', 'activeThread', 'messages', 'totalMessages', 'lastAt', 'me', 'canManageLock', 'scope', 'counts', 'hiddenByMe', 'canHide'));
    }

    public function storeThread(Request $r)
    {
        $data = $r->validate([
            'title' => 'required|string|max:180',
        ]);

        $thread = ChatThread::create([
            'title'     => $data['title'],
            'author_id' => Auth::id(),
            'is_locked' => false,
        ]);

        session(['toast' => [
            'type' => 'success',
            'message' => 'สร้างกระทู้ใหม่เรียบร้อยแล้ว',
            'title' => 'บันทึกสำเร็จ'
        ]]);

        return redirect()->route('chat.index', ['thread_id' => $thread->id]);
    }

    public function show(ChatThread $thread)
    {
        return redirect()->route('chat.index', ['thread_id' => $thread->id]);
    }

    public function messages(Request $r, ChatThread $thread)
    {
        $afterId = $r->integer('after_id');

        $query = $thread->messages()
            ->with('user:id,name')
            ->orderBy('id', 'asc');

        if ($afterId) {
            $query->where('id', '>', $afterId);
        }

        return response()->json($query->take(100)->get());
    }

    public function storeMessage(Request $r, ChatThread $thread)
    {
        // ถ้าล็อกแล้ว ห้ามโพสต์
        abort_if($thread->is_locked, 403, 'กระทู้นี้ถูกล็อก ไม่สามารถส่งข้อความได้');

        $data = $r->validate([
            'body' => 'required|string|max:3000',
        ]);

        $message = $thread->messages()->create([
            'user_id' => Auth::id(),
            'body'    => $data['body'],
        ]);

        $message->load('user:id,name');

        // Advance the sender's own read pointer (the API path already did this;
        // without it their unread badge never clears for their own posts).
        if ($message->user_id) {
            $this->markThreadRead((int) $message->user_id, (int) $thread->id, (int) $message->id, reappear: true);
        }

        SafeBroadcast::send(new \App\Events\ChatMessageSent($message));

        return back();
    }

    public function myUpdates(Request $request)
    {
        $u = $request->user();

        $threads = ChatThread::query()
            ->forTheWidget((int) $u->id)   // ตั้งเอง หรือเคยคอมเมนต์ - ไม่รวมที่ซ่อนไว้ และที่ล็อกแล้วอ่านหมดแล้ว
            ->with(['messages' => function ($q) {
                $q->with('user:id,name')->latest('id')->limit(1);
            }])
            ->withCount('messages')
            ->latest('updated_at')
            ->limit(15)
            ->get();

        $pointers = $this->readPointers((int) $u->id, $threads->pluck('id')->all());

        $items = $threads->map(function ($t) use ($pointers) {
            $last = $t->messages->first();
            return [
                'id'              => $t->id,
                'title'           => $t->title ?? ('กระทู้ #' . $t->id),
                'show_url'        => route('chat.show', $t),
                'hide_url'        => route('chat.hide', $t),
                'unread'          => $this->unreadCount((int) $t->id, $pointers[$t->id] ?? null, (int) ($t->messages_count ?? 0)),
                'last_user_name'  => $last?->user?->name,
                'last_user_avatar'=> $last?->user?->avatar_thumb_url,
                'last_body'       => $last?->body,
                'last_created_at' => optional($last?->created_at)->toIso8601String(),
            ];
        })->values();

        return response()->json($items);
    }

    // ========= Lock / Unlock =========

    public function lock(ChatThread $thread)
    {
        $this->authorizeLocking($thread);

        $thread->is_locked = true;
        $thread->save();

        session(['toast' => [
            'type' => 'success',
            'message' => 'ล็อกกระทู้เรียบร้อยแล้ว ผู้ใช้อื่นจะไม่สามารถส่งข้อความได้',
            'title' => 'ล็อกกระทู้'
        ]]);

        return back();
    }

    public function unlock(ChatThread $thread)
    {
        $this->authorizeLocking($thread);

        $thread->is_locked = false;
        $thread->save();

        session(['toast' => [
            'type' => 'success',
            'message' => 'ปลดล็อกกระทู้เรียบร้อยแล้ว เปิดรับการสนทนาตามปกติ',
            'title' => 'ปลดล็อกกระทู้'
        ]]);

        return back();
    }

    public function destroy(ChatThread $thread)
    {
        $user = Auth::user();

        if (! $user || $user->role !== 'admin') {
            // AccessDeniedHttpException, not abort(403): bootstrap/app.php turns that one into a toast on the page the user was on
            throw new \Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException('เฉพาะผู้ดูแลระบบเท่านั้นที่ลบกระทู้ได้');
        }

        $thread->delete();

        session(['toast' => [
            'type' => 'success',
            'message' => 'ลบกระทู้เรียบร้อยแล้ว ข้อมูลทั้งหมดถูกซ่อนจากระบบ',
            'title' => 'ลบกระทู้'
        ]]);

        return redirect()->route('chat.index');
    }

    // ========= Hide from / show again in "กระทู้ที่มีส่วนร่วม" (per person; nothing is deleted) =========

    public function hide(Request $request, ChatThread $thread)
    {
        $userId = (int) Auth::id();

        if (! ChatThread::involving($userId)->whereKey($thread->id)->exists()) {
            return $this->hiddenAnswer($request, false, 'คุณยังไม่ได้มีส่วนร่วมในกระทู้นี้ จึงไม่มีอะไรให้ซ่อน', 'warning', 422);
        }

        $this->hideThread($userId, (int) $thread->id);

        return $this->hiddenAnswer($request, true, 'ซ่อนกระทู้นี้จากกระทู้ที่มีส่วนร่วมแล้ว ยังหาเจอและอ่านได้ในแท็บทั้งหมด และจะกลับมาเมื่อคุณพิมพ์ในกระทู้นี้อีกครั้ง');
    }

    public function unhide(Request $request, ChatThread $thread)
    {
        $this->showThreadAgain((int) Auth::id(), (int) $thread->id);

        return $this->hiddenAnswer($request, false, 'แสดงกระทู้นี้ในกระทู้ที่มีส่วนร่วมอีกครั้งแล้ว');
    }

    /** JSON for the widget's fetch, a flashed toast for a form post. */
    protected function hiddenAnswer(Request $request, bool $hidden, string $message, string $type = 'success', int $status = 200)
    {
        if ($request->expectsJson()) {
            return response()->json(['hidden' => $hidden, 'message' => $message], $status);
        }

        session(['toast' => ['type' => $type, 'message' => $message, 'title' => $hidden ? 'ซ่อนกระทู้' : 'กระทู้ที่มีส่วนร่วม']]);

        return back();
    }

    protected function authorizeLocking(ChatThread $thread): void
    {
        // Locking is open to any signed-in non-member (see HandlesChatReads).
        $this->assertCanManageThread();
    }
}
