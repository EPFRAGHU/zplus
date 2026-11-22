<?php

namespace App\Services\Masters;

use App\Models\Approval;
use App\Models\Employee;
use App\Models\Post;
use Illuminate\Validation\ValidationException;

/**
 * No more staff on a post than sanctioned (owner, 22 Nov 2026): with 4 sanctioned, a 5th person cannot be placed.
 * "Taken" counts Active / On-notice staff on the post, plus new staff waiting for their first approval and staff
 * waiting for an approved move onto the post, so a post cannot be over-filled through the approval queue either.
 * Posts already over-filled before this rule are not changed; their staff can still be edited, nobody new is added.
 */
final class PostCapacity
{
    public const ON_POST = ['active', 'notice'];

    /** People holding or waiting for a place on this post, leaving out $except (the employee being edited). */
    public function taken(Post $post, ?Employee $except = null, bool $withWaiting = true): int
    {
        $exceptId = $except?->id;
        $count = Employee::query()->where('post_id', $post->id)->whereIn('status', self::ON_POST)
            ->when($exceptId, fn ($q) => $q->whereKeyNot($exceptId))->count();
        if (! $withWaiting) {
            return $count;
        }
        // New staff saved Inactive until the owner approves them.
        $count += Approval::query()->where('type', 'new_employee')->where('status', 'pending')
            ->whereHas('employee', fn ($q) => $q->where('post_id', $post->id)->whereNotIn('status', self::ON_POST)
                ->when($exceptId, fn ($e) => $e->whereKeyNot($exceptId)))->count();
        // Staff whose move onto this post is waiting for approval.
        $count += Approval::query()->where('type', 'employee_change')->where('status', 'pending')
            ->when($exceptId, fn ($q) => $q->where('employee_id', '<>', $exceptId))->get()
            ->filter(fn ($a) => (string) ($a->changes['post_id'][1] ?? '') === (string) $post->id)->count();

        return $count;
    }

    public function hasRoom(Post $post, ?Employee $except = null, int $adding = 1, bool $withWaiting = true): bool
    {
        return $this->taken($post, $except, $withWaiting) + $adding <= (int) $post->sanctioned_qty;
    }

    /** The message shown when a post is full. */
    public function fullMessage(Post $post, ?int $taken = null): string
    {
        $post->loadMissing('project');
        $taken ??= $this->taken($post);

        return "Post already filled: {$post->designation} ({$post->project->code}) has {$post->sanctioned_qty} sanctioned and {$taken} already placed or waiting for approval. "
            .'Raise the sanctioned number on the work order, or move someone off this post / set them Inactive first.';
    }

    /** Refuses a placement on a full post (field "post_id" on the form). */
    public function assertRoom(Post $post, ?Employee $except = null, int $adding = 1, string $field = 'post_id', bool $withWaiting = true): void
    {
        $taken = $this->taken($post, $except, $withWaiting);
        if ($taken + $adding > (int) $post->sanctioned_qty) {
            throw ValidationException::withMessages([$field => $this->fullMessage($post, $taken)]);
        }
    }

    /**
     * Does saving these details put someone on the post (new, moved onto it, or made Active / On notice again)?
     * Edits of someone already on the post (name, bank, …) never count, so over-filled posts can still be tidied up.
     */
    public static function placing(?Employee $before, array $after): bool
    {
        $postId = $after['post_id'] ?? $before?->post_id;
        $status = $after['status'] ?? $before?->status ?? 'active';
        if (! $postId || ! in_array($status, self::ON_POST, true)) {
            return false;
        }

        return ! $before || (int) $before->post_id !== (int) $postId || ! in_array($before->status, self::ON_POST, true);
    }
}
