<?php

namespace App\Support;

use Illuminate\Support\Collection;

final class MarketInstrumentCatalog
{
    /** @return Collection<int, array{symbol:string,name:string,exchange:string,type:string,currency:?string,unit:?string}> */
    public function all(): Collection
    {
        return collect(config('market.instruments', []))->values();
    }

    /** @return Collection<int, array{symbol:string,name:string,exchange:string,type:string,currency:?string,unit:?string}> */
    public function search(?string $query = null): Collection
    {
        $needle = mb_strtolower(trim($query ?? ''));

        if ($needle === '') {
            return $this->all();
        }

        return $this->all()->filter(static function (array $instrument) use ($needle): bool {
            $searchable = mb_strtolower(implode(' ', [
                $instrument['symbol'],
                $instrument['name'],
                $instrument['exchange'],
                $instrument['type'],
            ]));

            return str_contains($searchable, $needle);
        })->values();
    }

    /** @return array{symbol:string,name:string,exchange:string,type:string,currency:?string,unit:?string}|null */
    public function find(string $symbol): ?array
    {
        foreach ($this->all() as $instrument) {
            if (strcasecmp($instrument['symbol'], trim($symbol)) === 0) {
                return $instrument;
            }
        }

        return null;
    }

    /** @return array<int, string> */
    public function symbols(): array
    {
        return $this->all()->pluck('symbol')->all();
    }
}
