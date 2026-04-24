<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class DeleteTempFile implements ShouldQueue
{
    use Queueable;

    protected $path;

    public function __construct($path)
    {
        $this->path = $path;
    }

    public function handle()
    {
        if (file_exists($this->path)) {
            unlink($this->path);
        }
    }

}
