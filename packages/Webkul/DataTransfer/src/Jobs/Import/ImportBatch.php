<?php

namespace Webkul\DataTransfer\Jobs\Import;

use Illuminate\Bus\Batchable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Webkul\DataTransfer\Helpers\Import as ImportHelper;

class ImportBatch extends TenantAwareJob
{
    use Batchable, Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
use App\Jobs\TenantAwareJob;

    /**
     * Create a new job instance.
     *
     * @param  mixed  $importBatch
     * @return void
     */
    public function __construct(protected $importBatch)
    {
        $this->importBatch = $importBatch;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    protected function handleJob()
    {
        $typeImported = app(ImportHelper::class)
            ->setImport($this->importBatch->import)
            ->getTypeImporter();

        $typeImported->importBatch($this->importBatch);
    }
}
