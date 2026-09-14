<?php

namespace App\GraphQL\Mutations;

use App\Services\RecordingIngestionService;
use Illuminate\Support\Facades\Validator;

class RecordingMutation
{
    public function __construct(private RecordingIngestionService $ingestion) {}

    public function ingest($_, array $args): array
    {
        $input = Validator::make($args['input'], [
            'projectKey' => ['required', 'string', 'max:64'],
            'visitorId' => ['required', 'string', 'max:80'],
            'sessionId' => ['required', 'string', 'max:80'],
            'url' => ['required', 'url', 'max:4096'],
            'referrer' => ['nullable', 'string', 'max:4096'],
            'viewportWidth' => ['nullable', 'integer', 'between:1,10000'],
            'viewportHeight' => ['nullable', 'integer', 'between:1,10000'],
            'userAgent' => ['nullable', 'string', 'max:1000'],
            'encoding' => ['required', 'in:JSON,GZIP_BASE64'],
            'payload' => ['required', 'string', 'max:8000000'],
        ])->validate();

        return $this->ingestion->ingest($input, request()->header('Origin') ?: request()->header('Referer'));
    }
}
