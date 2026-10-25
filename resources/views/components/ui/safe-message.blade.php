@props(['value'])
{{ preg_match('/SQL|trace|exception|stack|server|servidor|\.php\b/i', (string) $value) ? 'No fue posible completar la operación. Revisa los datos e intenta nuevamente.' : $value }}
