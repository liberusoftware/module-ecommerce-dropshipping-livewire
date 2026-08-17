{{--
    What has left, and what has been promised. Nothing about who is sending it
    and nothing about what it cost the store.
--}}
<section class="dropshipping-order-despatch" aria-labelledby="dropshipping-order-despatch-heading">
    <h2 id="dropshipping-order-despatch-heading">Despatch</h2>

    @if ($failure !== null)
        <p class="dropshipping-failure" role="alert">{{ $failure }}</p>
    @elseif ($despatches === [])
        {{-- One answer for a wrong order, somebody else's order, an erased
             destination, and an order with nothing to despatch. --}}
        <p class="dropshipping-empty">There is no despatch information for this order.</p>
    @else
        <ul class="dropshipping-despatches">
            @foreach ($despatches as $despatch)
                <li class="dropshipping-despatch" wire:key="despatch-{{ $despatch['key'] }}">
                    <p class="dropshipping-despatch-status">{{ $despatch['status'] }}</p>

                    @if ($despatch['promisedBy'] !== null)
                        <p class="dropshipping-promise">Promised to leave by {{ $despatch['promisedBy'] }}.</p>
                    @else
                        {{-- No promise was made. An estimate invented here would be
                             the page promising on the store's behalf. --}}
                        <p class="dropshipping-promise dropshipping-promise--none">No despatch date has been promised for this.</p>
                    @endif

                    @if ($despatch['despatchedOn'] !== null)
                        <p class="dropshipping-despatched-on">It all left on {{ $despatch['despatchedOn'] }}.</p>

                        {{-- null is not false: "we cannot say" and "it was late"
                             are different sentences and neither stands in for the
                             other. --}}
                        @if ($despatch['onTime'] === true)
                            <p class="dropshipping-timeliness">That was on or before the date promised.</p>
                        @elseif ($despatch['onTime'] === false)
                            <p class="dropshipping-timeliness">That was later than the date promised.</p>
                        @else
                            <p class="dropshipping-timeliness dropshipping-timeliness--unknown">There was no promised date to measure that against.</p>
                        @endif
                    @endif

                    @if ($despatch['lines'] !== [])
                        {{-- Part of a purchase leaving is not the purchase
                             leaving. The quantities are here so the difference is
                             checkable rather than asserted. --}}
                        <table class="dropshipping-despatch-lines">
                            <caption>What has left so far</caption>
                            <thead>
                                <tr>
                                    <th scope="col">Item</th>
                                    <th scope="col">Ordered</th>
                                    <th scope="col">Despatched</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($despatch['lines'] as $line)
                                    <tr wire:key="line-{{ $despatch['key'] }}-{{ $line['lineRef'] }}">
                                        <th scope="row">{{ $line['lineRef'] }}</th>
                                        <td>{{ $line['ordered'] }}</td>
                                        <td>{{ $line['despatched'] }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    @endif

                    @if ($despatch['tracking'] !== [])
                        <ul class="dropshipping-tracking">
                            @foreach ($despatch['tracking'] as $tracking)
                                <li wire:key="tracking-{{ $despatch['key'] }}-{{ $tracking['reference'] }}">
                                    @if ($tracking['carrier'] !== null)
                                        {{ $tracking['carrier'] }} &mdash; {{ $tracking['reference'] }}
                                    @else
                                        {{-- A reference with no carrier named. Guessing
                                             one would send somebody to the wrong site. --}}
                                        {{ $tracking['reference'] }} <span class="dropshipping-tracking-carrier--none">(we have not been told which carrier)</span>
                                    @endif
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </li>
            @endforeach
        </ul>
    @endif
</section>
