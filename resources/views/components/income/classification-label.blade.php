@props(['code'])
@php($labels = require resource_path('views/components/income/classification-labels.php'))
{{ $labels[$code] ?? $code }}
