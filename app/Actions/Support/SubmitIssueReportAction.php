<?php

namespace App\Actions\Support;

use App\Models\IssueReport;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SubmitIssueReportAction
{
    /** @param array{module: string, description: string, expected_behavior: string} $payload */
    public function execute(User $reporter, array $payload, ?UploadedFile $attachment = null): IssueReport
    {
        return DB::transaction(function () use ($reporter, $payload, $attachment): IssueReport {
            $attachmentPath = $attachment instanceof UploadedFile
                ? $attachment->store('issue-attachments', 'local')
                : null;

            return IssueReport::query()->create([
                'protocol' => $this->uniqueProtocol(),
                'user_id' => $reporter->id,
                'module' => $payload['module'],
                'description' => $payload['description'],
                'expected_behavior' => $payload['expected_behavior'],
                'attachment_path' => $attachmentPath,
            ]);
        });
    }

    private function uniqueProtocol(): string
    {
        $date = today()->format('Ymd');

        for ($attempt = 0; $attempt < 10; $attempt++) {
            $protocol = sprintf('SUP-%s-%s', $date, Str::upper(Str::random(4)));

            if (! IssueReport::query()->where('protocol', $protocol)->exists()) {
                return $protocol;
            }
        }

        return sprintf('SUP-%s-%s', $date, Str::upper(Str::random(8)));
    }
}
