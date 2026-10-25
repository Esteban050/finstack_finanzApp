import test from 'node:test';
import assert from 'node:assert/strict';
import fs from 'node:fs';
import { execFileSync } from 'node:child_process';
import { passwordChecks, validateAuth, secondsRemaining } from '../auth/rules.js';
import { contrastCases } from '../utils/contrast.js';

const valid = {nombres:'María José', apellidos:'Gómez', tipo_documento:'CC',numero_documento:'ABC12345',correo:'prueba@example.invalid',password:'ClaveDistinta!9',password_confirmation:'ClaveDistinta!9',acepta_politica:'1'};
test('registro: nombres, documento sin máscara, consentimiento y contraseña', () => {
    assert.deepEqual(validateAuth('register',valid),{});
    assert.ok(validateAuth('register',{...valid,apellidos:'Apellido 2'}).apellidos);
    assert.ok(validateAuth('register',{...valid,numero_documento:'12.345.678'}).numero_documento);
    assert.ok(validateAuth('register',{...valid,acepta_politica:undefined}).acepta_politica);
    assert.equal(validateAuth('register',{...valid,password_confirmation:'distinta'}).password_confirmation,'Las contraseñas no coinciden');
    assert.ok(validateAuth('register',{...valid,correo:'a'.repeat(255)+'@example.invalid'}).correo);
});
test('contraseña: requisitos, datos personales y contexto ausente en reset', () => {
    const context={correo:'persona@example.invalid',documento:'DOC12345'};
    assert.ok(Object.values(passwordChecks('UnaClave!9x',context)).every(Boolean));
    assert.equal(passwordChecks('Persona!9999',context).personal,false);
    assert.equal(passwordChecks('DOC12345!abcD',context).personal,false);
    assert.equal(passwordChecks('UnaClave!9x').personal,null);
    assert.equal(passwordChecks('A!1'+'a'.repeat(62),context).length,false);
    for (const password of ['abc','sinmayuscula9!','SINMINUSCULA9!','SinDigito!','SinEspecial9']) assert.ok(Object.values(passwordChecks(password,context)).includes(false));
    assert.deepEqual(validateAuth('reset',{password:'UnaClave!9x',password_confirmation:'UnaClave!9x'}),{});
});
test('MFA, recuperación, política y cuenta regresiva no inventan estados', () => {
    assert.ok(validateAuth('mfa',{codigo:'12345'}).codigo);
    assert.deepEqual(validateAuth('mfa',{codigo:'000123'}),{});
    assert.ok(validateAuth('policy',{}).acepto);
    assert.deepEqual(validateAuth('recovery',{correo:valid.correo}),{});
    assert.equal(secondsRemaining(61000,1000),60);
    assert.equal(secondsRemaining(61000,60999),1);
    assert.equal(secondsRemaining(61000,62000),0);
});
test('tokens claros AA: texto 4.5 y controles/foco 3', () => {
    const css=fs.readFileSync('resources/css/themes.css','utf8');
    const color=(name)=>css.match(new RegExp('--'+name+':\\s*([^;]+)'))[1];
    for(const item of contrastCases(color)) assert.ok(item.ratio>=item.minimum,`${item.foreground}/${item.background}: ${item.ratio}`);
});
test('Blade auth real: campos del contrato, CSRF, servidor literal y sin rutas ficticias', {skip:!fs.existsSync('vendor/autoload.php')}, () => {
    const rendered=JSON.parse(execFileSync('php',['scripts/render-auth-fixtures.php'],{encoding:'utf8',maxBuffer:5e6}));
    for(const name of ['login','register','forgot-password','reset-password','mfa']) {
        assert.match(rendered[name],/name="_token"/);
        assert.match(rendered[name],/auth-brand-panel auth-panel-bg/);
        assert.match(rendered[name],/Política de tratamiento de datos personales/);
        const ids=[...rendered[name].matchAll(/\bid="([^"]+)"/g)].map(m=>m[1]); assert.equal(ids.length,new Set(ids).size,`IDs únicos en ${name}`);
    }
    assert.match(rendered['forgot-password'],/name="correo"/); assert.doesNotMatch(rendered['forgot-password'],/name="email"/);
    assert.match(rendered['reset-password'],/action="[^"]*\/reset-password"/); assert.match(rendered['reset-password'],/value="token-ficticio-sin-cuenta"/);
    assert.doesNotMatch(rendered.login,/recordar_dispositivo|Recordar este dispositivo|Recordarme|social|passkey/i);
    assert.match(rendered.mfa,/Recordar este dispositivo por 30 días/);
    assert.match(rendered['blocked-login'],/Tu cuenta se encuentra bloqueada temporalmente por intentos fallidos\. Intenta más tarde\./);
    assert.match(rendered['duplicate-register'],/No fue posible completar el registro con los datos suministrados\./);
    assert.match(rendered['mfa-error'],/Código incorrecto\. Te quedan 4 intentos\./);
    assert.match(rendered.verified,/Cuenta verificada/); assert.match(rendered.expired,/El enlace no es válido o expiró/);
    assert.match(rendered['recovery-status'],/Si el correo está registrado, recibirás un enlace/);
    assert.doesNotMatch(rendered.welcome,/auth-brand-panel|Controla tus ingresos/);
    assert.doesNotMatch(rendered.register,/name="(?:nombres|apellidos|numero_documento|correo)"[^>]*value="[^"]+"/);
    assert.doesNotMatch(rendered['confirm-password'],/<form/);
    assert.match(rendered['change-password'],/name="current_password"/);
    assert.match(rendered['change-password'],/name="_method" value="PUT"/);
    for (const html of Object.values(rendered)) for (const form of html.matchAll(/<form[^>]*method="POST"[^>]*>([\s\S]*?)<\/form>/g)) assert.match(form[1],/name="_token"/,'CSRF en cada POST');
});
