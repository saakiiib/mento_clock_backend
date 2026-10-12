<?php
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
return Application::configure(basePath:dirname(__DIR__))
 ->withRouting(web:__DIR__.'/../routes/web.php',api:__DIR__.'/../routes/api.php',commands:__DIR__.'/../routes/console.php',health:'/up')
 ->withMiddleware(function(Middleware $middleware){$middleware->alias(['platform.admin'=>App\Http\Middleware\PlatformAdminOnly::class,'active.business'=>App\Http\Middleware\ActiveBusiness::class,'business.admin'=>App\Http\Middleware\BusinessAdmin::class,'workspace.access'=>App\Http\Middleware\RequireWorkspaceAccess::class,'module.enabled'=>App\Http\Middleware\RequireModule::class,'role.permission'=>App\Http\Middleware\RequireWorkspacePermission::class]);})
 ->withExceptions(function(Exceptions $exceptions){})
 ->create();
