<?php

namespace App\Http\Controllers\Applicant;

use App\Http\Controllers\Controller;
use App\Http\Requests\UploadRequirementRequest;
use App\Models\Application;
use App\Models\ApplicationRequirement;
use App\Models\RequirementType;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class RequirementController extends Controller
{
    public function upload(UploadRequirementRequest $request, Application $application, RequirementType $type): RedirectResponse
    {
        $this->authorize('update', $application);

        $file = $request->file('file');
        $ext = $file->getClientOriginalExtension();
        $path = sprintf(
            'applications/%d/%s-%s.%s',
            $application->getKey(),
            $type->code,
            Str::uuid(),
            $ext,
        );

        DB::transaction(function () use ($application, $type, $file, $path): void {
            $existing = $application->requirements()
                ->where('requirement_type_id', $type->getKey())
                ->first();

            // Replace the old file on disk if the slot already had one.
            if ($existing && $existing->stored_path) {
                Storage::disk('local')->delete($existing->stored_path);
            }

            $file->storeAs(dirname($path), basename($path), 'local');

            $application->requirements()->updateOrCreate(
                ['requirement_type_id' => $type->getKey()],
                [
                    'original_filename' => $file->getClientOriginalName(),
                    'stored_path' => $path,
                    'mime' => $file->getClientMimeType(),
                    'size_kb' => (int) ceil($file->getSize() / 1024),
                    'checklist_status' => ApplicationRequirement::CHECK_COMPLETE,
                    'correction_note' => null,
                    'uploaded_at' => now(),
                ],
            );
        });

        return redirect()
            ->route('applications.edit', $application)
            ->with('success', 'Document uploaded.');
    }

    public function remove(Application $application, RequirementType $type): RedirectResponse
    {
        $this->authorize('update', $application);

        $requirement = $application->requirements()
            ->where('requirement_type_id', $type->getKey())
            ->first();

        if ($requirement) {
            DB::transaction(function () use ($requirement): void {
                if ($requirement->stored_path) {
                    Storage::disk('local')->delete($requirement->stored_path);
                }
                $requirement->delete();
            });
        }

        return redirect()
            ->route('applications.edit', $application)
            ->with('success', 'Document removed.');
    }

    public function file(ApplicationRequirement $applicationRequirement): StreamedResponse
    {
        $this->authorize('view', $applicationRequirement->application);

        abort_if($applicationRequirement->stored_path === null, 404);

        return Storage::disk('local')->download(
            $applicationRequirement->stored_path,
            $applicationRequirement->original_filename,
        );
    }
}
