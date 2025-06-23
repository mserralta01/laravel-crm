<?php

namespace App\Services\Tenant;

use App\Models\Tenant\Tenant;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Mail;

class TenantEmailService
{
    /**
     * Current tenant instance
     *
     * @var \App\Models\Tenant\Tenant|null
     */
    protected $tenant;
    
    /**
     * Original mail configuration
     *
     * @var array
     */
    protected $originalConfig;
    
    /**
     * Create a new service instance
     *
     * @param \App\Models\Tenant\Tenant|null $tenant
     */
    public function __construct(Tenant $tenant = null)
    {
        $this->tenant = $tenant ?: app()->bound('tenant') ? app('tenant') : null;
        $this->originalConfig = config('mail');
    }
    
    /**
     * Configure mail settings for the current tenant
     *
     * @return void
     */
    public function configureTenantMail()
    {
        if (!$this->tenant) {
            return;
        }
        
        // Get tenant email settings
        $emailSettings = $this->tenant->email_settings ?: [];
        
        // Set mail driver
        if (!empty($emailSettings['driver'])) {
            Config::set('mail.default', $emailSettings['driver']);
        }
        
        // Configure SMTP settings
        if ($emailSettings['driver'] === 'smtp') {
            Config::set([
                'mail.mailers.smtp.host' => $emailSettings['smtp_host'] ?? config('mail.mailers.smtp.host'),
                'mail.mailers.smtp.port' => $emailSettings['smtp_port'] ?? config('mail.mailers.smtp.port'),
                'mail.mailers.smtp.encryption' => $emailSettings['smtp_encryption'] ?? config('mail.mailers.smtp.encryption'),
                'mail.mailers.smtp.username' => $emailSettings['smtp_username'] ?? config('mail.mailers.smtp.username'),
                'mail.mailers.smtp.password' => $emailSettings['smtp_password'] ?? config('mail.mailers.smtp.password'),
            ]);
        }
        
        // Configure SendGrid settings
        if ($emailSettings['driver'] === 'sendgrid') {
            Config::set([
                'services.sendgrid.key' => $emailSettings['sendgrid_api_key'] ?? config('services.sendgrid.key'),
            ]);
        }
        
        // Set from address and name
        Config::set([
            'mail.from.address' => $emailSettings['from_address'] ?? config('mail.from.address'),
            'mail.from.name' => $emailSettings['from_name'] ?? $this->tenant->name,
        ]);
        
        // Set reply-to address
        if (!empty($emailSettings['reply_to_address'])) {
            Config::set([
                'mail.reply_to.address' => $emailSettings['reply_to_address'],
                'mail.reply_to.name' => $emailSettings['reply_to_name'] ?? $this->tenant->name,
            ]);
        }
        
        // Clear mail manager instance to apply new configuration
        Mail::forgetMailers();
    }
    
    /**
     * Reset mail configuration to original settings
     *
     * @return void
     */
    public function resetMailConfiguration()
    {
        Config::set('mail', $this->originalConfig);
        Mail::forgetMailers();
    }
    
    /**
     * Get tenant-specific email template
     *
     * @param string $templateKey
     * @param array $defaultData
     * @return array
     */
    public function getTenantEmailTemplate($templateKey, $defaultData = [])
    {
        if (!$this->tenant) {
            return $defaultData;
        }
        
        // Check if tenant has custom email templates
        $template = $this->tenant->emailTemplates()
            ->where('key', $templateKey)
            ->where('is_active', true)
            ->first();
        
        if ($template) {
            return [
                'subject' => $template->subject,
                'body' => $template->body,
                'variables' => $template->variables,
            ];
        }
        
        return $defaultData;
    }
    
    /**
     * Get tenant email footer
     *
     * @return string
     */
    public function getTenantEmailFooter()
    {
        if (!$this->tenant) {
            return '';
        }
        
        $footer = $this->tenant->getSetting('email_footer');
        
        if ($footer) {
            return $footer;
        }
        
        // Default footer with tenant branding
        return sprintf(
            '<p style="text-align: center; color: #666; font-size: 12px;">%s<br>%s</p>',
            $this->tenant->name,
            $this->tenant->website ?: ''
        );
    }
    
    /**
     * Validate tenant email configuration
     *
     * @return array
     */
    public function validateEmailConfiguration()
    {
        $errors = [];
        
        if (!$this->tenant) {
            $errors[] = 'No tenant context found';
            return $errors;
        }
        
        $emailSettings = $this->tenant->email_settings ?: [];
        
        if (empty($emailSettings['driver'])) {
            $errors[] = 'Email driver not configured';
        }
        
        if ($emailSettings['driver'] === 'smtp') {
            if (empty($emailSettings['smtp_host'])) {
                $errors[] = 'SMTP host not configured';
            }
            if (empty($emailSettings['smtp_port'])) {
                $errors[] = 'SMTP port not configured';
            }
        }
        
        if ($emailSettings['driver'] === 'sendgrid' && empty($emailSettings['sendgrid_api_key'])) {
            $errors[] = 'SendGrid API key not configured';
        }
        
        if (empty($emailSettings['from_address'])) {
            $errors[] = 'From email address not configured';
        }
        
        return $errors;
    }
    
    /**
     * Test email configuration by sending a test email
     *
     * @param string $toEmail
     * @return bool
     * @throws \Exception
     */
    public function testEmailConfiguration($toEmail)
    {
        $this->configureTenantMail();
        
        try {
            Mail::raw('This is a test email from ' . $this->tenant->name, function ($message) use ($toEmail) {
                $message->to($toEmail)
                    ->subject('Test Email Configuration');
            });
            
            return true;
        } catch (\Exception $e) {
            throw new \Exception('Failed to send test email: ' . $e->getMessage());
        } finally {
            $this->resetMailConfiguration();
        }
    }
}