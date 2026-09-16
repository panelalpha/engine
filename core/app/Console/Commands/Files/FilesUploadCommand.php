<?php

namespace App\Console\Commands\Files;

use App\Console\Commands\Concerns\DispatchesApiRoute;
use Illuminate\Console\Command;
use Illuminate\Http\UploadedFile;

class FilesUploadCommand extends Command
{
    use DispatchesApiRoute;

    /** The old spelling still answers, so nothing scripted against it breaks. */
    protected $aliases = ['files:upload'];

    protected $signature = 'project:file:upload
                            {project : Project username}
                            {file : Local file to upload}
                            {--path= : Destination directory inside the project}';

    protected $description = 'Upload a local file into a project (POST /projects/{username}/files/upload)';

    public function handle(): int
    {
        $project = (string) $this->argument('project');
        $file = (string) $this->argument('file');
        $path = (string) ($this->option('path') ?? '');

        if ($path === '') {
            $this->error('--path is required (the destination directory inside the project)');
            return 1;
        }
        if (!is_file($file)) {
            $this->error("Local file not found: {$file}");
            return 1;
        }

        // The endpoint takes multipart/form-data. UploadedFile in test mode
        // skips the is_uploaded_file() check, which only ever holds for a real
        // POST through PHP-FPM.
        $upload = new UploadedFile($file, basename($file), null, null, true);

        $response = $this->dispatchApiRoute(
            'POST',
            '/projects/' . rawurlencode($project) . '/files/upload',
            ['path' => $path],
            ['file' => $upload]
        );

        if ($response->getStatusCode() >= 400) {
            $this->error($this->errorMessage($response));
            return 1;
        }

        $this->info(sprintf('Uploaded %s to %s', basename($file), rtrim($path, '/') . '/' . basename($file)));
        return 0;
    }
}
