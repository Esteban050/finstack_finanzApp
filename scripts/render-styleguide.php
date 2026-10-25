<?php
/**
 * Adaptador de fixtures exclusivamente de desarrollo. Renderiza las plantillas
 * reales, sin instalar Laravel ni cargar/modificar el backend. No acepta entradas
 * HTTP. Solo admite el subconjunto Blade usado por estos componentes; una
 * directiva no soportada produce un error en vez de una representación falsa.
 */
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
const SG_ROOT = __DIR__.'/../resources/views/';

final class SgHtml {
    public function __construct(public string $value) {}
    public function __toString(): string { return $this->value; }
}
function sg_e(mixed $value): string {
    return $value instanceof SgHtml || $value instanceof SgAttributes ? (string) $value : htmlspecialchars((string) ($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
final class SgAttributes {
    public function __construct(public array $values) {}
    public function has(string $key): bool { return array_key_exists($key, $this->values); }
    public function class(string|array $classes): self {
        if (is_array($classes)) $classes = implode(' ', array_map(fn($key, $value) => is_int($key) ? $value : ($value ? $key : ''), array_keys($classes), $classes));
        return $this->merge(['class' => $classes]);
    }
    public function merge(array $defaults): self {
        $values = array_merge($defaults, $this->values);
        if (isset($defaults['class'], $this->values['class'])) $values['class'] = $defaults['class'].' '.$this->values['class'];
        return new self($values);
    }
    public function __toString(): string {
        $output = [];
        foreach ($this->values as $key => $value) {
            if ($value === false || $value === null) continue;
            $output[] = sg_e($key).'="'.($value === true ? '' : sg_e($value)).'"';
        }
        return implode(' ', $output);
    }
}
final class SgErrors { public function first(string $name): string { return ''; } }

// Datos explícitos de la guía, no contratos ni respuestas del backend.
function old(string $name, mixed $fallback = null): mixed { return $fallback; }
function asset(string $path): string { return '/'.$path; }
function resource_path(string $path): string { return __DIR__.'/../resources/'.$path; }
function session(string $name): mixed { return null; }
function auth(): object { return new class { public function check(): bool { return true; } }; }
function request(): object { return new class {
    public function routeIs(string ...$names): bool { return in_array('dashboard', $names, true); }
    public function except(array $keys): array { return []; }
}; }
function route(string $name, string $id = ''): string {
    // Destinos existentes, solo referencias visuales. La guía intercepta la navegación.
    return match ($name) {
        'dashboard' => '/dashboard', 'ingresos.index' => '/ingresos',
        'gastos.index' => '/gastos', 'gastos.categorias.index' => '/gastos/categorias',
        'logout' => '/logout', 'politica.mostrar' => '/politica-datos', 'ingresos.fuentes.index' => '/ingresos/fuentes',
        'ingresos.confirmar' => '/ingresos/'.$id.'/confirmar', 'ingresos.edit' => '/ingresos/'.$id.'/editar', 'ingresos.destroy' => '/ingresos/'.$id,
        default => throw new RuntimeException('Ruta de fixture no declarada: '.$name),
    };
}
function url(): object { return new class { public function current(): string { return '/dev/styleguide'; } }; }
function data_get(array|object $value, string $key): mixed { return is_array($value) ? ($value[$key] ?? null) : ($value->$key ?? null); }

final class SgPaginator {
    public function __construct(private int $page = 2, private int $total = 43) {}
    public function total(): int { return $this->total; }
    public function currentPage(): int { return $this->page; }
    public function lastPage(): int { return max(1, (int) ceil($this->total / 20)); }
    public function perPage(): int { return 20; }
    public function previousPageUrl(): ?string { return $this->page > 1 ? '#tabla' : null; }
    public function hasMorePages(): bool { return $this->page < $this->lastPage(); }
    public function nextPageUrl(): ?string { return $this->hasMorePages() ? '#tabla' : null; }
    public function withQueryString(): self { return $this; }
}

/** Lee expresiones equilibradas, incluidas comillas y paréntesis anidados. */
function sg_expression(string $source, int $start): array {
    $depth = 0; $quote = null; $length = strlen($source);
    for ($i = $start; $i < $length; $i++) {
        $char = $source[$i];
        if ($quote !== null) {
            if ($char === '\\') { $i++; continue; }
            if ($char === $quote) $quote = null;
            continue;
        }
        if ($char === '"' || $char === "'") { $quote = $char; continue; }
        if ($char === '(') $depth++;
        if ($char === ')' && --$depth === 0) return [substr($source, $start + 1, $i - $start - 1), $i + 1];
    }
    throw new RuntimeException('Expresión Blade sin cierre.');
}
function sg_attributes(string $source): string {
    preg_match_all('/([:@\w.-]+)(?:\s*=\s*("[^"]*"|\x27[^\x27]*\x27))?/', $source, $matches, PREG_SET_ORDER);
    $parts = [];
    foreach ($matches as $match) {
        $key = $match[1]; $value = isset($match[2]) ? substr($match[2], 1, -1) : true;
        if (str_starts_with($key, ':')) { $key = substr($key, 1); $expression = $value; }
        else $expression = var_export($value, true);
        // Blade convierte nombres kebab-case de props a camelCase.
        $key = preg_replace_callback('/-([a-z])/', fn($m) => strtoupper($m[1]), $key);
        // Conservar nombres de atributos HTML/Alpine que no son props.
        if (preg_match('/^(data-|aria-|@)/', ltrim($match[1], ':'))) $key = ltrim($match[1], ':');
        $parts[] = var_export($key, true).' => ('.$expression.')';
    }
    return '['.implode(', ', $parts).']';
}
function sg_compile(string $source): string {
    $source = preg_replace('/\{\{--[\s\S]*?--\}\}/', '', $source);
    $source = preg_replace_callback('/<x-([\w.-]+)\b((?:[^>"\x27]|"[^"]*"|\x27[^\x27]*\x27)*)\s*(\/?)>/', function($m) {
        $attrs = rtrim($m[2]); $self = str_ends_with($attrs, '/') || $m[3] === '/'; $attrs = rtrim($attrs, '/ ');
        $entry = "['name'=>".var_export($m[1], true).",'attrs'=>".sg_attributes($attrs).']';
        return $self ? '<?php echo sg_component('.var_export($m[1], true).', '.sg_attributes($attrs).'); ?>' : '<?php $__sgStack[] = '.$entry.'; ob_start(); ?>';
    }, $source);
    $source = preg_replace('/<\/x-[\w.-]+\s*>/', '<?php $__sgSlot = ob_get_clean(); $__sgEntry = array_pop($__sgStack); echo sg_component($__sgEntry["name"], $__sgEntry["attrs"], $__sgSlot); ?>', $source);
    $source = preg_replace('/\{!!\s*([\s\S]*?)\s*!!\}/', '<?php echo $1; ?>', $source);
    $source = preg_replace('/\{\{\s*([\s\S]*?)\s*\}\}/', '<?php echo sg_e($1); ?>', $source);
    $source = preg_replace('/@php\s*\n([\s\S]*?)@endphp/', '<?php $1 ?>', $source);
    $withExpression = ['if','elseif','foreach','for','isset','switch','case','include','selected','required','disabled','php','method'];
    // De derecha a izquierda para no desplazar offsets aún por procesar.
    preg_match_all('/@('.implode('|', $withExpression).')\s*(?=\()/', $source, $matches, PREG_OFFSET_CAPTURE);
    for ($i = count($matches[0]) - 1; $i >= 0; $i--) {
        $offset = $matches[0][$i][1]; $name = $matches[1][$i][0];
        $start = $offset + strlen($matches[0][$i][0]); [$expr, $end] = sg_expression($source, $start);
        $replacement = match ($name) {
            'if','elseif','foreach','for','switch' => '<?php '.$name.'('.$expr.'): ?>',
            'isset' => '<?php if(isset('.$expr.')): ?>',
            'case' => '<?php case '.$expr.': ?>',
            'include' => '<?php echo sg_view('.$expr.', get_defined_vars()); ?>',
            'selected','required','disabled' => '<?php if('.$expr.') echo "'.$name.'"; ?>',
            'php' => '<?php '.$expr.'; ?>',
            'method' => '<input type="hidden" name="_method" value="<?php echo sg_e('.$expr.'); ?>">',
        };
        $source = substr_replace($source, $replacement, $offset, $end - $offset);
    }
    $source = strtr($source, ['@endif'=>'<?php endif; ?>','@endforeach'=>'<?php endforeach; ?>','@endfor'=>'<?php endfor; ?>','@endisset'=>'<?php endif; ?>','@endswitch'=>'<?php endswitch; ?>','@else'=>'<?php else: ?>','@break'=>'<?php break; ?>','@auth'=>'<?php if(auth()->check()): ?>','@endauth'=>'<?php endif; ?>','@csrf'=>'<!-- Fixture de desarrollo: no se envían formularios al backend. -->']);
    return preg_replace('/(<\?php switch\([\s\S]*?\): \?>)\s+(<\?php case)/', '$1$2', $source);
}
function sg_component(string $name, array $provided = [], string $content = ''): string {
    $file = SG_ROOT.'components/'.str_replace('.', '/', $name).'.blade.php';
    if (!is_file($file)) throw new RuntimeException('Componente inexistente: '.$name);
    $source = file_get_contents($file); $defaults = [];
    if (str_starts_with($source, '@props(')) {
        [$expr, $end] = sg_expression($source, 6); $props = eval('return '.$expr.';');
        foreach ($props as $key => $value) $defaults[is_int($key) ? $value : $key] = is_int($key) ? null : $value;
        $source = substr($source, $end);
    }
    $values = array_merge($defaults, array_intersect_key($provided, $defaults));
    $values['attributes'] = new SgAttributes(array_diff_key($provided, $defaults));
    $values['slot'] = new SgHtml($content); $values['errors'] = new SgErrors();
    return sg_eval($source, $values);
}
function sg_eval(string $source, array $values): string {
    extract($values, EXTR_SKIP); $__sgStack = []; $level = ob_get_level(); ob_start();
    try { eval('?>'.sg_compile($source)); return ob_get_clean(); }
    catch (Throwable $error) { while (ob_get_level() > $level) ob_end_clean(); throw $error; }
}
function sg_view(string $name, array $values = []): string {
    return sg_eval(file_get_contents(SG_ROOT.str_replace('.', '/', $name).'.blade.php'), $values);
}
try {
    echo sg_view('dev.styleguide', ['errors' => new SgErrors()]);
} catch (Throwable $error) {
    fwrite(STDERR, $error->getMessage().' in '.$error->getFile().':'.$error->getLine().PHP_EOL);
    exit(1);
}
