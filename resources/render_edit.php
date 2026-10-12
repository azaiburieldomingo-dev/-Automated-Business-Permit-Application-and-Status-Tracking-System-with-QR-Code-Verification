<?php

// Temporary: render the edit page to HTML for a visual check. Delete after use.
require __DIR__.'/vendor/autoload.php';
$app = require __DIR__.'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

use App\Models\Application;
use App\Models\RequirementType;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\ViewErrorBag;

$out = sys_get_temp_dir().DIRECTORY_SEPARATOR.'bansud-edit';
@mkdir($out);
view()->share('errors', new ViewErrorBag);

$application = new Application(['business_name' => 'Juan Store']);
$application->id = 999;
$application->status = Application::STATUS_DRAFT;
$application->setRelation('requirements', collect());

file_put_contents("$out/edit.html", view('applicant.edit', [
    'application' => $application,
    'requirementTypes' => RequirementType::where('is_active', true)->get(),
])->render());

echo $out;
