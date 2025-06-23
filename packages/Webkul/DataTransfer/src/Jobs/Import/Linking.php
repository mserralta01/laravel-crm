<?php

namespace Webkul\DataTransfer\Jobs\Import;

use Illuminate\Contracts\Queue\ShouldQueue;
use Webkul\DataTransfer\Helpers\Import as ImportHelper;

class Linking extends TenantAwareJob
{
     InteractsWithQueue, Queueable, SerializesModels;
use App\Jobs\TenantAwareJob;

    /**
     * Create a new job instance.
     *
     * @param  mixed  $import
     * @return void
     */
    public function __construct(protected $import)
    {
        $this->import = $import;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    protected function handleJob()
    {
        app(ImportHelper::class)
            ->setImport($this->import)
            ->linking();
    }
}
