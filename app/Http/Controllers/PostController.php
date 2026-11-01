<?php

namespace App\Http\Controllers;

use App\Models\Post;
use App\Models\Project;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class PostController extends Controller
{
    public function store(Request $request, Project $project): RedirectResponse
    {
        Gate::authorize('manage-masters');
        $project->posts()->create($this->validated($request));

        return back()->with('status', 'Post added.');
    }

    public function update(Request $request, Post $post): RedirectResponse
    {
        Gate::authorize('manage-masters');
        $post->update($this->validated($request));

        return back()->with('status', 'Post saved.');
    }

    public function destroy(Post $post): RedirectResponse
    {
        Gate::authorize('manage-masters');
        if ($post->employees()->exists()) {
            return back()->withErrors(['post' => 'Move or remove the employees on this post first.']);
        }
        $post->delete();

        return back()->with('status', 'Post removed.');
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'designation' => ['required', 'string', 'max:255'],
            'sanctioned_qty' => ['required', 'integer', 'min:0', 'max:999'],
            'rate' => ['required', 'numeric', 'min:0', 'decimal:0,2'],
        ]);
    }
}
