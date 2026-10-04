<?php
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
return Application::configure(basePath: dirname(__DIR__))
 ->withRouting(web: __DIR__.'/../routes/web.php', api: __DIR__.'/../routes/api.php', commands: __DIR__.'/../routes/console.php', health: '/up')
 ->withMiddleware(function (Middleware $middleware) {
  // Trust the hosting proxy (cPanel/Cloudflare/Nginx) so HTTPS is
  // detected correctly for secure cookies and redirects.
  // Set TRUSTED_PROXIES=* (or a comma-separated IP list) in production.
  // Leave it empty for local development.
  if ($proxies = env('TRUSTED_PROXIES')) {
   $middleware->trustProxies(at: $proxies === '*' ? '*' : explode(',', $proxies));
  }
 })
 ->withExceptions(function (Exceptions $exceptions) {})->create();
