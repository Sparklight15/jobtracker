<div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
    <x-stat-card name="total_apply" label="Total apply" />
    <x-stat-card name="overall_conversion" label="Conversion keseluruhan" />
    <x-stat-card name="ghosting_rate" label="Rasio ghosting" />
</div>

<div class="mt-4 grid gap-4 lg:grid-cols-3">
    <x-chart-panel name="per_stage" title="Pernah mencapai tiap tahap" />
    <x-chart-panel name="stage_conversion" title="Conversion antar tahap" />
    <x-chart-panel name="status_now" title="Status saat ini" />
</div>