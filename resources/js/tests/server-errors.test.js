import test from 'node:test';
import assert from 'node:assert/strict';
import { execFileSync } from 'node:child_process';

test('RNF-012: el manejador real devuelve JSON seguro en 404/405/419/429/500 sin consultar la BD', () => {
    const rendered = JSON.parse(execFileSync('php', ['-r', String.raw`
        require 'vendor/autoload.php';
        $app = require 'bootstrap/app.php';
        $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
        Illuminate\Support\Facades\DB::connection()->beforeExecuting(function () { throw new RuntimeException('La prueba no puede consultar la BD.'); });
        $handler=$app->make(Illuminate\Contracts\Debug\ExceptionHandler::class);
        $request=Illuminate\Http\Request::create('/qa-error-probe','GET',[],[],[],['HTTP_ACCEPT'=>'application/json']);
        $output=[];
        foreach([404,405,419,429,500] as $status){
            $error=$status===500 ? new Illuminate\Database\QueryException('pgsql','select QA_PRIVATE_SQL',[],new PDOException('QA_PRIVATE_HOST')) : new Symfony\Component\HttpKernel\Exception\HttpException($status,'QA_PRIVATE_EXCEPTION');
            $response=$handler->render($request,$error);
            $output[$status]=['status'=>$response->getStatusCode(),'body'=>json_decode($response->getContent(),true)];
        }
        echo json_encode($output,JSON_THROW_ON_ERROR);
    `], {encoding:'utf8',maxBuffer:2e6}));
    for (const status of [404,405,419,429,500]) {
        assert.equal(rendered[status].status,status);
        assert.equal(typeof rendered[status].body.message,'string');
        assert.doesNotMatch(JSON.stringify(rendered[status].body),/QA_PRIVATE|SQLSTATE|exception|trace|Connection|\.php/);
    }
    assert.match(rendered[500].body.error_id,/^[0-9a-f-]{36}$/);
    assert.match(rendered[419].body.message,/sesión expiró/);
});
