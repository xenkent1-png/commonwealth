<?php

namespace App\Http\Controllers\Investor;

use App\Http\Controllers\Controller;
use App\Models\WatchlistItem;
use App\Support\MarketInstrumentCatalog;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class WatchlistController extends Controller
{
    public function store(Request $request, MarketInstrumentCatalog $catalog): RedirectResponse
    {
        $validated = $request->validate([
            'symbol' => [
                'required', 'string', 'max:24', Rule::in($catalog->symbols()),
                Rule::unique('watchlist_items', 'symbol')->where(
                    fn ($query) => $query->where('user_id', $request->user()->id)
                ),
            ],
        ]);

        $instrument = $catalog->find($validated['symbol']);
        abort_if($instrument === null, 422);

        $user = $request->user();
        $sortOrder = (int) $user->watchlistItems()->max('sort_order') + 10;

        try {
            $user->watchlistItems()->create([
                'symbol' => $instrument['symbol'],
                'company_name' => $instrument['name'],
                'exchange' => $instrument['exchange'],
                'instrument_type' => $instrument['type'],
                'sort_order' => $sortOrder,
            ]);
        } catch (QueryException $exception) {
            $sqlState = (string) ($exception->errorInfo[0] ?? $exception->getCode());
            if (! in_array($sqlState, ['23000', '23505'], true)) {
                throw $exception;
            }

            throw ValidationException::withMessages([
                'symbol' => 'This instrument is already in your watchlist.',
            ]);
        }

        return back()->with('status', $instrument['symbol'].' added to My Stocks.');
    }

    public function update(Request $request, int $watchlistItem): RedirectResponse
    {
        $item = $request->user()->watchlistItems()->findOrFail($watchlistItem);
        $validated = $request->validate([
            'notes' => ['nullable', 'string', 'max:500'],
            'sort_order' => ['required', 'integer', 'min:0', 'max:65535'],
        ]);

        $item->update($validated);

        return back()->with('status', $item->symbol.' watchlist entry updated.');
    }

    public function destroy(Request $request, int $watchlistItem): RedirectResponse
    {
        $item = $request->user()->watchlistItems()->findOrFail($watchlistItem);
        $symbol = $item->symbol;
        $item->delete();

        return back()->with('status', $symbol.' removed from My Stocks.');
    }
}
