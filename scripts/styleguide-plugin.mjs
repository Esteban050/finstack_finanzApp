import { execFile } from 'node:child_process';
import { promisify } from 'node:util';
import path from 'node:path';

const execute = promisify(execFile);

// No rutas Laravel: solo middleware del servidor de desarrollo del bundler.
export function styleguidePlugin() {
    return {
        name: 'scfp-styleguide-development-only',
        apply: 'serve',
        configureServer(server) {
            server.middlewares.use(async (req, res, next) => {
                if (!['/dev/styleguide', '/dev/styleguide/'].includes(req.url?.split('?')[0])) return next();
                try {
                    const { stdout } = await execute('php', [path.resolve('scripts/render-styleguide.php')], { cwd: server.config.root, maxBuffer: 4 * 1024 * 1024 });
                    const html = await server.transformIndexHtml('/dev/styleguide', stdout);
                    res.setHeader('Content-Type', 'text/html; charset=utf-8');
                    res.setHeader('Cache-Control', 'no-store');
                    res.end(html);
                } catch (error) {
                    server.config.logger.error(`Styleguide: ${error.message}`);
                    res.statusCode = 500;
                    res.end('No fue posible mostrar la guía de desarrollo. Revisa la consola de Vite.');
                }
            });
        },
    };
}
