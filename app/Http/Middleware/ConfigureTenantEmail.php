<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Services\Tenant\TenantEmailService;

class ConfigureTenantEmail
{
    /**
     * The email service instance
     *
     * @var \App\Services\Tenant\TenantEmailService
     */
    protected $emailService;
    
    /**
     * Create a new middleware instance
     *
     * @param \App\Services\Tenant\TenantEmailService $emailService
     */
    public function __construct(TenantEmailService $emailService)
    {
        $this->emailService = $emailService;
    }
    
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure(\Illuminate\Http\Request): (\Illuminate\Http\Response|\Illuminate\Http\RedirectResponse)  $next
     * @return \Illuminate\Http\Response|\Illuminate\Http\RedirectResponse
     */
    public function handle(Request $request, Closure $next)
    {
        if (app()->bound('tenant')) {
            $this->emailService->configureTenantMail();
        }
        
        return $next($request);
    }
    
    /**
     * Handle tasks after the response has been sent to the browser.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Illuminate\Http\Response  $response
     * @return void
     */
    public function terminate($request, $response)
    {
        $this->emailService->resetMailConfiguration();
    }
}