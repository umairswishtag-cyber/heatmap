<?php

namespace App\Jobs;

use App\Services\RecordingProcessorService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ProcessRecordingChunk implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 120;

    public function __construct(public int $projectId, public string $batchId, public array $batch) {}

    public function handle(RecordingProcessorService $processor): void
    {
        $processor->process($this->projectId, $this->batchId, $this->batch);
    }
}
