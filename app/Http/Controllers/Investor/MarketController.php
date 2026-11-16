<?php

namespace App\Http\Controllers\Investor;

use App\Http\Controllers\Controller;
use App\Support\MarketInstrumentCatalog;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MarketController extends Controller
{
    public function show(Request $request, MarketInstrumentCatalog $catalog, string $symbol): View
    {
        $instrument = $catalog->find($symbol);
        abort_if($instrument === null, 404);

        $watchlistItemId = $request->user()->watchlistItems()
            ->where('symbol', $instrument['symbol'])
            ->value('id');

        return view('investor.markets.show', [
            'instrument' => $instrument,
            'isWatchlisted' => $watchlistItemId !== null,
            'watchlistItemId' => $watchlistItemId,
        ]);
    }
}
