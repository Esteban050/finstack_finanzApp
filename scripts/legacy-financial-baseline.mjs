import { createHash } from 'node:crypto';

// Deuda heredada del merge 85e06d6. NO significa que estas fórmulas estén corregidas.
// El PO excluye Renta de esta tarea; no reescribir cálculos de otros módulos para silenciar lint.
// Solo se admite la instantánea exacta revisada. Cualquier modificación exige corregir
// la infracción; no actualizar estos hashes para admitir código monetario nuevo.
const legacy = Object.freeze({
    "resources/views/inversiones/index.blade.php": "3be2d9e5bfc3eee57326c8d55b0aec5f1987318602cdd0409b1c4844abf484f5",
    "resources/views/inversiones/show.blade.php": "f4699e4907d56825410957a477397831a3bdb0d4c7614ea913fffff485705058",
    "resources/views/inversiones/portafolio.blade.php": "a1d1dae10ded17c1caa58d6aabd0ab4dfb634146be1f197bce5ddcf7a360d8c0",
    "resources/views/inversiones/reportes/retenciones.blade.php": "21b45b38b4929a09b0790da722e54090bd774dfdce6f5115605e89f158a0d59d",
    "resources/views/inversiones/reportes/utilidades.blade.php": "800f59d081df7defc2ed9f2dc1bb89d8d83390ca110a774698b055208baf1337",
    "resources/views/renta/complementaria.blade.php": "bbaf294cd2588ce86014a89a94939e59fd506cb6b291c6a6ccda1a625b3d9ab9",
    "resources/views/renta/estimacion.blade.php": "e35ac71dca61dcc869f2d666483a928d005092e9b3dc274229596e218c9edd6c"
});

export function financialLintStatus(file, source) {
    if (!/parseFloat\(|\(float\)/.test(source)) return 'clean';
    const normalized = file.replaceAll('\\', '/');
    const hash = createHash('sha256').update(source.replaceAll('\r\n', '\n')).digest('hex');
    return legacy[normalized] === hash ? 'legacy' : 'error';
}
