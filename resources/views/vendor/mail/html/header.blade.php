@props(['url'])
@php($brandLogo = app(\App\Services\SiteBranding::class)->logoEmailUrl())
<tr>
<td class="header">
<a href="{{ $url }}" style="display: inline-block;">
@if ($brandLogo)
<img src="{{ $brandLogo }}" alt="{{ trim($slot) }}" style="max-height: 46px; width: auto; height: auto; margin: 10px 0;">
@else
{!! $slot !!}
@endif
</a>
</td>
</tr>
