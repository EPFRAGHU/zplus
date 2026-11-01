<?php

namespace App\Http\Controllers;

use App\Models\Agency;
use App\Models\Client;
use App\Models\GemContract;
use App\Models\Post;
use App\Models\Project;
use App\Services\Masters\QuickPostCreator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class PostController extends Controller
{
    /** All posts across agencies, clients and projects. */
    public function index(Request $request): View
    {
        $agencyId = $request->integer('agency') ?: null;
        $clientId = $request->integer('client') ?: null;
        $posts = Post::query()->with('project.contract.agency', 'project.contract.client')->withCount('activeEmployees')
            ->whereHas('project.contract', fn ($q) => $q
                ->when($agencyId, fn ($c) => $c->where('agency_id', $agencyId))
                ->when($clientId, fn ($c) => $c->where('client_id', $clientId)))
            ->when($request->integer('project'), fn ($q, $id) => $q->where('project_id', $id))
            ->get()
            ->sortBy(fn ($p) => $p->project->contract->agency->name.'|'.$p->project->contract->client->name.'|'.$p->project->name.'|'.$p->designation)->values();

        return view('posts.index', ['posts' => $posts, 'agencyId' => $agencyId, 'clientId' => $clientId] + self::pickerData());
    }

    /** "Add new post" window: creates the contract and project too when asked. Answers JSON for the window, or redirects. */
    public function quickStore(Request $request, QuickPostCreator $creator): JsonResponse|RedirectResponse
    {
        Gate::authorize('manage-masters');
        $data = $request->validate([
            'agency_id' => ['required', 'exists:agencies,id'],
            'client_id' => ['required', 'exists:clients,id'],
            'contract_mode' => ['required', Rule::in(['existing', 'new'])],
            'gem_contract_id' => ['required_if:contract_mode,existing', 'nullable', 'exists:gem_contracts,id'],
            'bid_no' => ['required_if:contract_mode,new', 'nullable', 'string', 'max:40'],
            'contract_no' => ['required_if:contract_mode,new', 'nullable', 'string', 'max:40', 'unique:gem_contracts,contract_no'],
            'billing_type' => ['nullable', Rule::in(['monthly', 'daily'])],
            'project_mode' => ['required', Rule::in(['existing', 'new'])],
            'project_id' => ['required_if:project_mode,existing', 'nullable', 'exists:projects,id'],
            'project_name' => ['required_if:project_mode,new', 'nullable', 'string', 'max:255'],
            'project_code' => ['nullable', 'alpha_num', 'max:12'],
            'pi_name' => ['nullable', 'string', 'max:255'],
            'designation' => ['required', 'string', 'max:255'],
            'sanctioned_qty' => ['required', 'integer', 'min:1', 'max:999'],
            'rate' => ['required', 'numeric', 'min:0', 'decimal:0,2'],
        ], [
            'client_id.required' => 'Choose the client first.',
            'gem_contract_id.required_if' => 'Choose the GeM contract, or switch to "New contract".',
            'bid_no.required_if' => 'Enter the GeM bid number.',
            'contract_no.required_if' => 'Enter the GeM contract number.',
            'contract_no.unique' => 'A contract with this number already exists. Choose it from the list instead.',
            'project_id.required_if' => 'Choose the project, or switch to "New project".',
            'project_name.required_if' => 'Enter the project name.',
        ]);
        $post = $creator->create($data)->load('project.contract');

        if ($request->expectsJson()) {
            $k = $post->project->contract;

            return response()->json(['post' => [
                'id' => $post->id, 'agency_id' => $k->agency_id, 'client_id' => $k->client_id,
                'label' => $k->bid_no.' · '.$post->label().' (0/'.$post->sanctioned_qty.' filled)',
            ]], 201);
        }

        return back()->with('status', 'Post added.');
    }

    /** Contracts and projects for the agency/client pickers in the "Add new post" window. */
    public static function pickerData(): array
    {
        return [
            'agencies' => Agency::query()->orderBy('name')->get(),
            'clients' => Client::query()->with('agency')->orderBy('name')->get(),
            'pickContracts' => GemContract::query()->orderBy('bid_no')->get(['id', 'agency_id', 'client_id', 'bid_no', 'contract_no']),
            'pickProjects' => Project::query()->orderBy('name')->get(['id', 'gem_contract_id', 'name', 'pi_name']),
        ];
    }

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
