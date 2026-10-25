<?php
// Leer las fuentes existentes: no duplicar ni redibujar el kit.
$tokens = file_get_contents(resource_path('css/tokens.css'));
preg_match_all('/--cat-([a-z]+):\s*(#[a-fA-F0-9]{6})/', $tokens, $colors, PREG_SET_ORDER);
$palette = [];
foreach ($colors as $color) $palette[$color[1]] = strtoupper($color[2]);
$registry = file_get_contents(resource_path('js/ui/icons/icons.js'));
preg_match('/export const ICONS = (\{.*?\});/s', $registry, $match);
$icons = array_keys(json_decode($match[1], true, flags: JSON_THROW_ON_ERROR));
return ['palette' => $palette, 'icons' => $icons, 'colorLabels' => ['emerald' => 'Esmeralda', 'sky' => 'Azul cielo', 'violet' => 'Violeta', 'pink' => 'Rosado', 'amber' => 'Ámbar', 'orange' => 'Naranja', 'red' => 'Rojo', 'teal' => 'Turquesa', 'lime' => 'Lima', 'indigo' => 'Índigo', 'rose' => 'Rosa', 'slate' => 'Gris pizarra']];
