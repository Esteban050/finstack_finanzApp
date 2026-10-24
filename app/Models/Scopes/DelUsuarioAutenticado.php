<?php

namespace App\Models\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use Illuminate\Support\Facades\Auth;

/**
 * RNG-01 Aislamiento de datos: un Usuario solo puede crear, consultar, modificar
 * o eliminar sus propios registros.
 *
 * El filtro vive en la capa de datos, así que cubre también los listados
 * (RF-024) y no solo el acceso a un registro puntual: olvidar un
 * where('usuario_id', ...) deja de ser una fuga de información.
 *
 * No hay excepción para el rol ADMIN a propósito: CA-018.2 exige que ninguna
 * ruta de administración devuelva datos financieros de los Usuarios, y RN-018.1
 * lo refuerza con el principio de minimización de datos.
 */
class DelUsuarioAutenticado implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        // Sin sesión no se filtra: los comandos programados (RN-022.2,
        // scfp:generar-recurrencias) corren por consola y necesitan ver todas
        // las filas. Las rutas web siempre pasan por el middleware `auth`.
        if (! Auth::check()) {
            return;
        }

        // qualifyColumn prefija la tabla del modelo, para que el filtro no quede
        // ambiguo cuando la consulta tiene joins.
        $builder->where($model->qualifyColumn('usuario_id'), Auth::id());
    }
}
