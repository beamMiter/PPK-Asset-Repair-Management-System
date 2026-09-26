<?php

namespace App\Http\Controllers;

use App\Events\ChatMessageDeleted;
use App\Events\ChatThreadDeleted;
use App\Events\ChatThreadLockChanged;
use App\Models\ChatMessage;
use App\Models\ChatModerationLog;
use App\Models\ChatThread;
use App\Support\ChatQuota;
use App\Support\SafeBroadcast;
use App\Traits\HandlesChatReads;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Support\Like;

class ChatController extends Controller
{
    use HandlesChatReads;

    public function index(Request $r)
    {
        $q = (string) $r->string('q');
        $scope = in_array($r->query('scope'), ['mine', 'hidden'], true) ? $r->query('scope') : 'all';   // 'mine': the threads I started or wrote in; 'hidden': the ones I hid from that
        $meId = (int) Auth::id();

        $threads = ChatThread::query()
            ->with('author:id,name')
            ->withCount('messages')
            ->with(['latestMessage' => fn($qq) => $qq->with('user:id,name')])
            ->when($scope === 'mine', fn($qq) => $qq->inMyList($meId))
            ->when($scope === 'hidden', fn($qq) => $qq->hiddenBy($meId))
            ->when($q, fn($qq) => $qq->where('title', 'like', Like::contains($q)))
            ->orderByDesc('created_at')
            ->paginate(15)
            ->withQueryString();

        $activeThreadId = $r->integer('thread_id');
        $activeThread = null;
        $hasEarlier = false;
        $messages = collect();
        $totalMessages = 0;
        $lastAt = null;

        if ($activeThreadId) {
            $activeThread = ChatThread::find($activeThreadId);
            if ($activeThread) {
                $messages = $activeThread->messages()
                    ->withTrashed()                 // a deleted message is drawn as "ข้อความนี้ถูกลบ", not left out
                    ->with('user:id,name')
                    ->orderByDesc('id')
                    ->take(50)
                    ->get()
                    ->reverse()
                    ->values();

                $totalMessages = $activeThread->messages()->count();
                // older ones than the 50 drawn: the page offers to load them (and does, when scrolled to the top)
                $hasEarlier = $messages->isNotEmpty()
                    && $activeThread->messages()->withTrashed()->where('id', '<', $messages->first()->id)->exists();
                $lastAt = $messages->last()?->created_at ?? $activeThread->created_at;

                // Opening a thread reads it. Without this, only posting a message ever advanced the pointer
                // (storeMessage below), so the widget's "ใหม่" badge for a thread never cleared just by looking at it.
                if ($lastMessageId = $activeThread->messages()->max('id')) {
                    $this->markThreadRead((int) Auth::id(), (int) $activeThread->id, (int) $lastMessageId);
                }
            }
        }

        $me = Auth::user();
        $canManageLock = $activeThread && $activeThread->canBeLockedBy($me);   // admins and the IT / repair team

        // the open thread and me: can I hide it from my list (I took part, and have not), or bring it back (I hid it)?
        $hiddenByMe = $activeThread ? $this->hasHiddenThread($meId, (int) $activeThread->id) : false;
        $canDelete = $activeThread && $activeThread->canBeDeletedBy($me);
        $canHide = $activeThread && ! $hiddenByMe && ChatThread::involving($meId)->whereKey($activeThread->id)->exists();

        // what each list holds, for the tabs (not narrowed by the search)
        $counts = ['all' => ChatThread::count(), 'mine' => ChatThread::inMyList($meId)->count(), 'hidden' => ChatThread::hiddenBy($meId)->count()];

        $threadQuota = ChatQuota::for($me);

        // "ใหม่ N" beside a thread of mine that has messages I have not read (the open thread was read above; a hidden one does not count)
        $listed = $threads->getCollection()->pluck('id')->all();
        $mineOnThisPage = ChatThread::inMyList($meId)->whereIn('chat_threads.id', $listed)->pluck('chat_threads.id')->all();
        $unread = array_intersect_key($this->unreadCountsFor($meId, $mineOnThisPage), array_flip($mineOnThisPage));

        return view('chat.index', compact('threads', 'activeThread', 'messages', 'totalMessages', 'lastAt', 'me', 'canManageLock', 'scope', 'counts', 'unread', 'hiddenByMe', 'canHide', 'canDelete', 'threadQuota', 'hasEarlier'));
    }

    public function storeThread(Request $r)
    {
        $data = $r->validate([
            'title' => 'required|string|max:180',
        ]);

        // so many new threads a day for each person (config/chat.php): asked BEFORE it is made, and told how many are left
        if (! ChatQuota::canStart($r->user())) {
            return back()->withInput()->with('toast', \App\Support\Toast::warning(ChatQuota::refusal(), 5000));
        }

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

    /**
     * The thread's messages for the page that has it open: NEWER than `after_id` (the poll), or - like scrolling up in any messenger -
     * the batch OLDER than `before_id` (30 by default, oldest first, deleted ones as placeholders), with `X-Has-More` saying whether there
     * are still older ones. Keyed by id, not by page number: a message that arrives meanwhile cannot shift what is loaded.
     */
    public function messages(Request $r, ChatThread $thread)
    {
        $beforeId = $r->integer('before_id');

        if ($beforeId) {
            $limit = max(1, min((int) $r->query('limit', 30), 100));
            $older = $thread->messages()->withTrashed()->with('user:id,name')
                ->where('id', '<', $beforeId)->orderByDesc('id')->take($limit + 1)->get();

            return response()->json($older->take($limit)->sortBy('id')->values()->map(fn (ChatMessage $m) => $m->toChatArray()))
                ->header('X-Has-More', $older->count() > $limit ? '1' : '0')
                ->header('X-Thread-Locked', $thread->is_locked ? '1' : '0');
        }

        $afterId = $r->integer('after_id');

        $query = $thread->messages()
            ->with('user:id,name')
            ->orderBy('id', 'asc');

        if ($afterId) {
            $query->where('id', '>', $afterId);
        }

        return response()->json($query->take(100)->get())
            ->header('X-Thread-Locked', $thread->is_locked ? '1' : '0');
    }

    public function storeMessage(Request $r, ChatThread $thread)
    {
        // A retry of a message that already went through (the answer was lost on the way): answer with that one - it was accepted when it
        // was sent, whatever has happened to the thread since - and save nothing twice.
        if ($replay = $this->messageOfAttempt((int) Auth::id(), $thread, $r->input('client_id'))) {
            return $r->expectsJson() ? response()->json($replay->toChatArray(), 200) : back();
        }

        // ถ้าล็อกแล้ว ห้ามโพสต์
        abort_if($thread->is_locked, 403, 'กระทู้นี้ถูกล็อก ไม่สามารถส่งข้อความได้');

        $data = $r->validate([
            'body' => 'required|string|max:3000',
            'client_id' => 'nullable|uuid',
        ]);

        [$message, $created] = $this->saveMessageOnce($thread, (int) Auth::id(), $data['body'], $data['client_id'] ?? null);
        if (! $created) {
            return $r->expectsJson() ? response()->json($message->toChatArray(), 200) : back();
        }

        // Advance the sender's own read pointer (the API path already did this;
        // without it their unread badge never clears for their own posts).
        if ($message->user_id) {
            $this->markThreadRead((int) $message->user_id, (int) $thread->id, (int) $message->id, reappear: true);
        }

        SafeBroadcast::send(new \App\Events\ChatMessageSent($message));

        // the page sends with fetch and draws the message from this; a plain form post still goes back to the page
        return $r->expectsJson() ? response()->json($message->toChatArray(), 201) : back();
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

    public function lock(Request $request, ChatThread $thread)
    {
        return $this->setLocked($request, $thread, true);
    }

    public function unlock(Request $request, ChatThread $thread)
    {
        return $this->setLocked($request, $thread, false);
    }

    /**
     * The page asks with fetch (JSON) so nobody reloads: it gets the new state back, and everyone else who has the thread open
     * hears it through ChatThreadLockChanged. A plain form post still works, with the flashed toast.
     */
    protected function setLocked(Request $request, ChatThread $thread, bool $locked)
    {
        $this->authorizeLocking($thread);

        $thread->is_locked = $locked;
        $thread->save();

        ChatModerationLog::record($locked ? ChatModerationLog::LOCK : ChatModerationLog::UNLOCK, $request->user(), $thread, request: $request);

        SafeBroadcast::send(new ChatThreadLockChanged((int) $thread->id, $locked));

        $toast = [
            'type' => 'success',
            'message' => $locked
                ? 'ล็อกกระทู้เรียบร้อยแล้ว ผู้ใช้อื่นจะไม่สามารถส่งข้อความได้'
                : 'ปลดล็อกกระทู้เรียบร้อยแล้ว เปิดรับการสนทนาตามปกติ',
            'title' => $locked ? 'ล็อกกระทู้' : 'ปลดล็อกกระทู้',
        ];

        if ($request->expectsJson()) {
            return response()->json(['is_locked' => $locked, 'message' => $toast['message'], 'title' => $toast['title']]);
        }

        session(['toast' => $toast]);

        return back();
    }

    public function destroy(Request $request, ChatThread $thread)
    {
        if (! $thread->canBeDeletedBy(Auth::user())) {
            // AccessDeniedHttpException, not abort(403): bootstrap/app.php turns that one into a toast on the page the user was on
            throw new \Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException('เฉพาะเจ้าของกระทู้และผู้ดูแลระบบเท่านั้นที่ลบกระทู้ได้');
        }

        $thread->delete();

        ChatModerationLog::record(ChatModerationLog::DELETE_THREAD, $request->user(), $thread, meta: ['own' => (int) $thread->author_id === (int) $request->user()->id], request: $request);

        SafeBroadcast::send(new ChatThreadDeleted((int) $thread->id));

        session(['toast' => [
            'type' => 'success',
            'message' => 'ลบกระทู้เรียบร้อยแล้ว ข้อมูลทั้งหมดถูกซ่อนจากระบบ',
            'title' => 'ลบกระทู้'
        ]]);

        return redirect()->route('chat.index');
    }

    // ========= Delete one message =========

    /**
     * Its author (while the thread is open) or a moderator deletes a message: it stays in the thread as "ข้อความนี้ถูกลบ" for everybody,
     * the text is gone from every page, and the deletion is written to the moderation record. It is a soft delete (the purge clears it later).
     */
    public function destroyMessage(Request $request, ChatThread $thread, ChatMessage $message)
    {
        abort_unless((int) $message->chat_thread_id === (int) $thread->id, 404);

        if (! $thread->canDeleteMessage($message, $request->user())) {
            throw new \Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException('เฉพาะเจ้าของข้อความและผู้ดูแลเท่านั้นที่ลบข้อความได้');
        }

        // deleting a message must not bring the thread to the top of the list ("updated"): the THREAD is what is not touched
        ChatThread::withoutTouching(fn () => $message->delete());

        ChatModerationLog::record(ChatModerationLog::DELETE_MESSAGE, $request->user(), $thread, $message, [
            'message_author_id' => (int) $message->user_id,
            'own' => (int) $message->user_id === (int) $request->user()->id,
        ], $request);

        SafeBroadcast::send(new ChatMessageDeleted((int) $thread->id, (int) $message->id));

        return $request->expectsJson() ? response()->json(['deleted' => true, 'id' => $message->id]) : back();
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
        $this->assertCanLock($thread);   // admins and the IT / repair team (see HandlesChatReads)
    }
}
