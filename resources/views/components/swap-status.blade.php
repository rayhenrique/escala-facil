@props(['swap'])
<span class="inline-flex flex-wrap gap-2"><x-status-badge :status="$swap->status" />@if ($swap->rectification_exists ?? ($swap->relationLoaded('rectification') && $swap->rectification !== null))<x-status-badge status="rectified" label="Retificada administrativamente" />@endif</span>
