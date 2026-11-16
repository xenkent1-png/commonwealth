@extends('layouts.app')
@section('title', 'Investor dashboard | Commonwealth')
@section('content')
<section class="portal-header">
    <div class="container-xl">
        <p class="eyebrow dark-eyebrow"><span></span> INVESTOR PORTAL</p>
        <h1>Your market,<br><em>in clear view.</em></h1>
        <p>Explore market instruments and manage your personal watchlist.</p>
    </div>
</section>

<main class="container-xl portal-wrap">
    @if (session('status'))
        <p class="market-feedback" role="status">{{ session('status') }}</p>
    @endif
    @if ($errors->has('symbol'))
        <p class="market-feedback market-feedback--error" role="alert">{{ $errors->first('symbol') }}</p>
    @endif

    <section class="market-watchlist" id="market-list" aria-labelledby="market-title">
        <div class="market-watchlist__heading">
            <div>
                <p class="market-eyebrow">MARKET</p>
                <h2 id="market-title">Stocks <span>{{ now()->format('F j') }}</span></h2>
                <p class="market-watchlist__intro">Browse supported stocks and market indices. Add the instruments you want to follow to My Stocks.</p>
            </div>
            <span class="market-data-state">Quotes unavailable</span>
        </div>

        <form class="market-search" method="GET" action="{{ route('investor.dashboard') }}" role="search">
            <label class="visually-hidden" for="market-search-input">Search stocks and indices</label>
            <input id="market-search-input" type="search" name="q" value="{{ $marketQuery }}" placeholder="Search by ticker or company" maxlength="80">
            <button type="submit" aria-label="Search market instruments">Search</button>
            @if ($marketQuery !== '')
                <a class="market-search__clear" href="{{ route('investor.dashboard') }}">Clear</a>
            @endif
        </form>

        <p class="market-data-notice">A market-data provider has not been configured. Prices, daily changes, and charts are withheld until real data is available.</p>

        <div class="market-rows" aria-label="Available market instruments">
            @forelse ($marketInstruments as $instrument)
                <article class="market-row">
                    <a class="market-row__main" href="{{ route('investor.stocks.show', ['symbol' => $instrument['symbol']]) }}">
                        <span class="market-row__identity">
                            <strong>{{ $instrument['symbol'] }}</strong>
                            <span>{{ $instrument['name'] }}</span>
                        </span>
                        <span class="market-row__chart"><span>Chart unavailable</span></span>
                        <span class="market-row__quote"><strong>—</strong><small>Quote unavailable</small></span>
                        <span class="market-row__change"><small>Daily change</small><strong>—</strong></span>
                    </a>
                    <div class="market-row__actions">
                        @if (in_array($instrument['symbol'], $watchlistSymbols, true))
                            <span class="market-saved">Saved</span>
                        @else
                            <form method="POST" action="{{ route('investor.watchlist.store') }}">
                                @csrf
                                <input type="hidden" name="symbol" value="{{ $instrument['symbol'] }}">
                                <button class="market-add-button" type="submit">Add</button>
                            </form>
                        @endif
                    </div>
                </article>
            @empty
                <p class="market-empty">No instruments match “{{ $marketQuery }}”. Try a ticker or company name.</p>
            @endforelse
        </div>
    </section>

    <section class="personal-watchlist" aria-labelledby="my-stocks-title">
        <div class="personal-watchlist__heading">
            <div>
                <p class="eyebrow dark-eyebrow"><span></span> YOUR LIST</p>
                <h2 id="my-stocks-title">My Stocks</h2>
                <p>Only you can see and manage the instruments saved here.</p>
            </div>
            <span class="personal-watchlist__count">{{ $watchlistItems->count() }} saved</span>
        </div>

        @if ($watchlistItems->isEmpty())
            <div class="personal-watchlist__empty">
                <h3>Your watchlist is ready when you are.</h3>
                <p>Add an instrument from the market list. Saved choices will still be here the next time you sign in.</p>
                <a href="#market-list">Explore the market <span aria-hidden="true">↗</span></a>
            </div>
        @else
            <div class="personal-watchlist__rows">
                @foreach ($watchlistItems as $item)
                    <article class="market-row market-row--personal">
                        <a class="market-row__main" href="{{ route('investor.stocks.show', ['symbol' => $item->symbol]) }}">
                            <span class="market-row__identity">
                                <strong>{{ $item->symbol }}</strong>
                                <span>{{ $item->company_name }}</span>
                                @if ($item->notes)<small class="market-row__note">{{ $item->notes }}</small>@endif
                            </span>
                            <span class="market-row__chart"><span>Chart unavailable</span></span>
                            <span class="market-row__quote"><strong>—</strong><small>Quote unavailable</small></span>
                            <span class="market-row__change"><small>Daily change</small><strong>—</strong></span>
                        </a>
                        <div class="market-row__actions market-row__actions--manage">
                            <details class="watchlist-edit">
                                <summary>Edit</summary>
                                <form method="POST" action="{{ route('investor.watchlist.update', ['watchlistItem' => $item->id]) }}">
                                    @csrf
                                    @method('PATCH')
                                    <label for="watchlist-note-{{ $item->id }}">Personal note</label>
                                    <textarea id="watchlist-note-{{ $item->id }}" name="notes" maxlength="500" rows="2">{{ $item->notes }}</textarea>
                                    <label for="watchlist-order-{{ $item->id }}">Display order</label>
                                    <input id="watchlist-order-{{ $item->id }}" type="number" name="sort_order" min="0" max="65535" value="{{ $item->sort_order }}" required>
                                    <button class="market-action-button" type="submit">Save changes</button>
                                </form>
                            </details>
                            <form method="POST" action="{{ route('investor.watchlist.destroy', ['watchlistItem' => $item->id]) }}" data-confirm="Remove {{ $item->symbol }} from My Stocks?">
                                @csrf
                                @method('DELETE')
                                <button class="market-action-button market-action-button--remove" type="submit">Remove</button>
                            </form>
                        </div>
                    </article>
                @endforeach
            </div>
        @endif
    </section>

    <div class="portal-summary">
        <article class="portal-card"><span>LEDGER BALANCE</span><h2>@forelse($balances as $currency => $amount){{ $currency }} {{ $amount }}@if(!$loop->last)<br>@endif @empty No posted balance @endforelse</h2><p>Calculated from posted ledger entries only.</p></article>
        <article class="portal-card"><span>POSTED LEDGER ENTRIES</span><h2>{{ number_format($recordCount) }}</h2><p>Entries linked to this account.</p></article>
        <article class="portal-card portal-note"><span>ACCOUNT STATUS</span><h2>{{ auth()->user()->status === 'active' ? 'Active' : 'Under review' }}</h2><p>Email verified {{ auth()->user()->email_verified_at?->toFormattedDateString() ?? 'Not available' }}.</p></article>
    </div>

    <div class="portal-panel dashboard-trend-panel">
        <div class="panel-head"><div><p class="eyebrow dark-eyebrow"><span></span> ACCOUNT ACTIVITY</p><h2>Posted ledger entries</h2><p>Monthly count of entries posted to your accounts. This chart shows activity volume, not investment performance.</p></div></div>
        @if(array_sum($activityTrend['values']) > 0)
            <div class="dashboard-chart-wrap"><canvas id="activity-trend-chart" aria-label="Monthly count of posted account ledger entries" role="img" data-labels="{{ implode('|', $activityTrend['labels']) }}" data-values="{{ implode(',', $activityTrend['values']) }}"></canvas></div>
        @else
            <p class="portal-empty dashboard-chart-empty">No posted activity in the last six months. Account activity will appear here when ledger records post.</p>
        @endif
    </div>

    <div class="portal-content">
        <div class="portal-panel"><div class="panel-head"><div><p class="eyebrow dark-eyebrow"><span></span> ACTIVITY</p><h2>Recent posted activity</h2></div></div>
            @if($activity->isEmpty())
                <p class="portal-empty">No posted ledger activity is recorded for this account yet.</p>
            @else
                <div class="activity-table-wrap">
                    <table class="activity-table">
                        <thead><tr><th scope="col">Reference</th><th scope="col">Activity</th><th scope="col">Date</th><th scope="col">Amount</th></tr></thead>
                        <tbody>
                        @foreach($activity as $entry)
                            @php
                                $creditNormal = in_array($entry->account->account_type, ['liability', 'equity', 'income'], true);
                                $isIncrease = $creditNormal ? $entry->direction === 'credit' : $entry->direction === 'debit';
                            @endphp
                            <tr>
                                <td><strong>{{ $entry->transaction->reference }}</strong><span>{{ str($entry->transaction->transaction_type)->replace('_', ' ')->title() }}</span></td>
                                <td>{{ $entry->transaction->description ?: 'Posted ledger entry' }}</td>
                                <td>{{ $entry->transaction->posted_at?->format('M j, Y') ?? $entry->created_at?->format('M j, Y') ?? 'Not available' }}</td>
                                <td class="{{ $isIncrease ? 'amount-positive' : 'amount-negative' }}">{{ $isIncrease ? '+' : '−' }}{{ $entry->currency }} {{ $entry->display_amount }}</td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
        <aside class="portal-panel portal-side"><p class="eyebrow dark-eyebrow"><span></span> NEXT STEPS</p><h2>Review before you invest.</h2><p>Read opportunity terms and their supporting documents. The platform is not currently accepting live deposits or investments.</p><a class="button button-navy" href="{{ route('opportunities.index') }}">Explore catalog <span>↗</span></a></aside>
    </div>
</main>
@endsection
