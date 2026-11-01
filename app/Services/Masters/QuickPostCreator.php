<?php

namespace App\Services\Masters;

use App\Models\Client;
use App\Models\GemContract;
use App\Models\Post;
use App\Models\Project;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Adds a post in one step, creating the GeM contract and/or project on the way when they do not exist yet.
 * Used by the "Add new post" window on the employee form and the Posts page.
 */
final class QuickPostCreator
{
    public function create(array $d): Post
    {
        $client = Client::query()->findOrFail($d['client_id']);
        if ((int) $client->agency_id !== (int) $d['agency_id']) {
            throw ValidationException::withMessages(['client_id' => 'This client belongs to a different agency.']);
        }

        return DB::transaction(function () use ($d, $client) {
            if (($d['contract_mode'] ?? 'existing') === 'new') {
                $contract = GemContract::query()->create([
                    'agency_id' => $client->agency_id, 'client_id' => $client->id,
                    'bid_no' => $d['bid_no'], 'contract_no' => $d['contract_no'],
                    'billing_type' => $d['billing_type'] ?? 'monthly',
                ]);
            } else {
                $contract = GemContract::query()->findOrFail($d['gem_contract_id']);
                if ((int) $contract->client_id !== (int) $client->id) {
                    throw ValidationException::withMessages(['gem_contract_id' => 'This GeM contract is with a different client.']);
                }
            }

            if (($d['project_mode'] ?? 'existing') === 'new') {
                $code = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', ($d['project_code'] ?? null) ?: $d['project_name']));
                $project = $contract->projects()->create([
                    'name' => $d['project_name'], 'code' => substr($code, 0, 12) ?: 'PRJ',
                    'title' => $d['project_name'], 'pi_name' => $d['pi_name'] ?? null,
                ]);
            } else {
                $project = Project::query()->findOrFail($d['project_id']);
                if ((int) $project->gem_contract_id !== (int) $contract->id) {
                    throw ValidationException::withMessages(['project_id' => 'This project is under a different GeM contract.']);
                }
            }

            return $project->posts()->create([
                'designation' => $d['designation'], 'sanctioned_qty' => $d['sanctioned_qty'], 'rate' => $d['rate'],
            ]);
        });
    }
}
