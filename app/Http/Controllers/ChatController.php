<?php

namespace App\Http\Controllers;

use App\Models\ChatThread;
use App\Traits\HandlesChatReads;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ChatController extends Controller
{
    use HandlesChatReads;

    public function index(Request $r)
    {
        $q = (string) $r->string('q');

        $threads = ChatThread::query()
            ->with('author:id,name')
            ->withCount('messages')
            ->with(['latestMessage' => fn($qq) => $qq->with('user:id,name')])
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
            }
        }

        $me = Auth::user();
        $canManageLock = $me && $me->role !== 'member';

        return view('chat.index', compact('threads', 'activeThread', 'messages', 'totalMessages', 'lastAt', 'me', 'canManageLock'));
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
        abort_if($thread->is_locked, 403, 'Thread locked');

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
            $this->markThreadRead((int) $message->user_id, (int) $thread->id, (int) $message->id);
        }

        broadcast(new \App\Events\ChatMessageSent($message));

        return back();
    }

    public function myUpdates(Request $request)
    {
        $u = $request->user();

        $threads = ChatThread::query()
            ->where(function ($q) use ($u) {
                $q->where('author_id', $u->id)
                  ->orWhereHas('messages', fn ($mm) => $mm->where('user_id', $u->id)); // เคยคอมเมนต์
            })
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
            'title' => 'Lock Thread'
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
            'title' => 'Unlock Thread'
        ]]);

        return back();
    }

    public function destroy(ChatThread $thread)
    {
        $user = Auth::user();

        if (! $user || $user->role !== 'admin') {
            abort(403, 'Forbidden. Only administrators can delete threads.');
        }

        $thread->delete();

        session(['toast' => [
            'type' => 'success',
            'message' => 'ลบกระทู้เรียบร้อยแล้ว ข้อมูลทั้งหมดถูกซ่อนจากระบบ',
            'title' => 'Delete Thread'
        ]]);

        return redirect()->route('chat.index');
    }

    protected function authorizeLocking(ChatThread $thread): void
    {
        // Locking is open to any signed-in non-member (see HandlesChatReads).
        $this->assertCanManageThread();
    }
}
