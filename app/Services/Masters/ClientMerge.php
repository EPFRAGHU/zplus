<?php

namespace App\Services\Masters;

use App\Models\Agency;
use App\Models\Client;
use App\Support\Activity\Activity;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Owner, 21 Nov 2026: each agency has a single client, "Administrative Officer NIHR - Regional Medical Research
 * Centre, Bhubaneswar". Moves every work order, employee and tax invoice of an agency's other clients onto one
 * client, renames it and removes the emptied clients. Amounts and bill numbers are not touched.
 */
class ClientMerge
{
    public const NAME = 'Administrative Officer NIHR - Regional Medical Research Centre, Bhubaneswar';

    /** Tables that point at a client. A new one must be added here or the emptied client cannot be deleted. */
    private const REFERENCES = ['gem_contracts' => 'work orders', 'employees' => 'employees', 'tax_invoices' => 'tax invoices'];

    /** @return Collection<int, array{client: Client, counts: array<string, int>}> */
    public function clients(Agency $agency): Collection
    {
        return Client::query()->where('agency_id', $agency->id)->orderBy('name')->get()
            ->map(fn (Client $c) => ['client' => $c, 'counts' => $this->counts($c)]);
    }

    /** The client to keep when nothing is chosen: one already named for NIHR, else the one with most work orders. */
    public function suggestedTarget(Agency $agency): ?Client
    {
        $rows = $this->clients($agency);

        return ($rows->first(fn ($r) => stripos($r['client']->name, 'NIHR') !== false)
            ?? $rows->sortByDesc(fn ($r) => $r['counts']['gem_contracts'])->first())['client'] ?? null;
    }

    /** @return array<string, int> moved row counts per table */
    public function merge(Agency $agency, Client $target, string $name): array
    {
        abort_unless($target->agency_id === $agency->id, 404);

        return DB::transaction(function () use ($agency, $target, $name) {
            $others = Client::query()->where('agency_id', $agency->id)->whereKeyNot($target->id)->get();
            $moved = array_fill_keys(array_keys(self::REFERENCES), 0);
            foreach ($others as $other) {
                $counts = $this->counts($other);
                foreach (array_keys(self::REFERENCES) as $table) {
                    $moved[$table] += DB::table($table)->where('client_id', $other->id)->update(['client_id' => $target->id]);
                }
                Activity::log('client_merged', $target, [], sprintf('%s merged into this client: %s', $other->name,
                    collect(self::REFERENCES)->map(fn ($label, $t) => $counts[$t].' '.$label)->implode(', ')), section: 'Clients');
                $other->delete();
            }
            if ($target->name !== $name) {
                $target->update(['name' => $name]);
            }

            return $moved;
        });
    }

    /** @return array<string, int> */
    private function counts(Client $client): array
    {
        return collect(self::REFERENCES)->map(fn ($label, $table) => DB::table($table)->where('client_id', $client->id)->count())->all();
    }

    public static function labels(): array
    {
        return self::REFERENCES;
    }
}
