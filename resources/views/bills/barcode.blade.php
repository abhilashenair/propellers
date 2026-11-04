@php($barcode = (new \App\AwbBarcode)->encode($number))
<div class="awb-barcode">
@if($barcode)
<svg xmlns="http://www.w3.org/2000/svg" role="img" aria-label="AWB barcode {{ $number }}" viewBox="0 0 {{ $barcode['width'] }} 60" width="{{ $barcode['width'] * 2 }}" height="60" preserveAspectRatio="none" shape-rendering="crispEdges">
<title>AWB {{ $number }}</title>
<rect width="{{ $barcode['width'] }}" height="60" fill="#fff"/>
@foreach($barcode['bars'] as $bar)<rect x="{{ $bar['x'] }}" y="5" width="{{ $bar['width'] }}" height="50" fill="#000"/>@endforeach
</svg>
@else
<small>Barcode unavailable for this AWB number’s characters.</small>
@endif
<strong class="awb-barcode-number">{{ $number }}</strong>
</div>
