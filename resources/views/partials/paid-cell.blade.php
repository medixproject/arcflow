@if($b['has_paid'])
    <span class="amount">{{ \App\Support\Decimal6::display($b['paid']) }}</span>
    @if($b['partial'])
        <div class="bar warn" role="img" aria-label="{{ $b['percent'] }} percent paid"><i style="width:{{ $b['percent'] }}%"></i></div>
        <div class="cell-sub">{{ \App\Support\Decimal6::display($b['remaining']) }} remaining</div>
    @elseif($b['excess'] !== '0.000000')
        <div class="cell-sub">+{{ \App\Support\Decimal6::display($b['excess']) }} over</div>
    @endif
@else
    <span class="muted">—</span>
@endif
