@extends('layouts.app')
@section('title', $instrument['name'].' | Commonwealth')
@section('content')
<section class="portal-header">
    <div class="container-xl">
        <p class="eyebrow dark-eyebrow"><span></span> MARKET DETAILS</p>
        <h1>{{ $instrument['symbol'] }}<br><em>{{ $instrument['name'] }}</em></h1>
        <p>{{ strtoupper($instrument['type']) }} · {{ $instrument['exchange'] }} · {{ $instrument['currency'] ? 'Quote currency: '.$instrument['currency'] : 'Quote unit: '.$instrument['unit'] }}</p>
    </div>
</section>

<main class="container-xl portal-wrap market-detail-wrap">
    <a class="market-back-link" href="{{ route('investor.dashboard') }}">← Back to Stocks</a>
    @if (session('status'))
        <p class="market-feedback" role="status">{{ session('status') }}</p>
    @endif
    @if ($errors->has('symbol'))
        <p class="market-feedback market-feedback--error" role="alert">{{ $errors->first('symbol') }}</p>
    @endif

    <section class="market-detail-card" aria-labelledby="instrument-detail-title">
        <div class="market-detail-card__heading">
            <div>
                <p class="market-eyebrow">INSTRUMENT</p>
                <h2 id="instrument-detail-title">{{ $instrument['name'] }}</h2>
                <p>{{ $instrument['symbol'] }} · {{ strtoupper($instrument['type']) }} · {{ $instrument['exchange'] }} · {{ $instrument['currency'] ? 'Quote currency: '.$instrument['currency'] : 'Quote unit: '.$instrument['unit'] }}</p>
            </div>
            <div class="market-detail-card__actions">
                @if ($isWatchlisted)
                    <form method="POST" action="{{ route('investor.watchlist.destroy', ['watchlistItem' => $watchlistItemId]) }}" data-confirm="Remove {{ $instrument['symbol'] }} from My Stocks?">
                        @csrf
                        @method('DELETE')
                        <button class="market-action-button market-action-button--remove" type="submit">Remove from My Stocks</button>
                    </form>
                @else
                    <form method="POST" action="{{ route('investor.watchlist.store') }}">
                        @csrf
                        <input type="hidden" name="symbol" value="{{ $instrument['symbol'] }}">
                        <button class="market-add-button" type="submit">Add to My Stocks</button>
                    </form>
                @endif
            </div>
        </div>

        <div class="market-detail-metrics">
            <article><span>Latest price</span><strong>Unavailable</strong></article>
            <article><span>Daily change</span><strong>Unavailable</strong></article>
            <article><span>Market status</span><strong>Unavailable</strong></article>
            <article><span>Last updated</span><strong>No quote timestamp</strong></article>
        </div>
        <p class="market-data-notice">A market-data provider has not been configured. This page shows instrument reference details only; it does not represent an owned position.</p>
    </section>

    <section class="market-detail-card market-history-card" aria-labelledby="history-title">
        <div>
            <p class="market-eyebrow">PRICE HISTORY</p>
            <h2 id="history-title">Historical performance</h2>
        </div>
        <div class="market-history-empty" role="status">
            <span aria-hidden="true">—</span>
            <p>Chart unavailable until a market-data source is configured.</p>
        </div>
    </section>
</main>
@endsection
