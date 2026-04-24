<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use App\Models\Area;

class StoreAreaFromPostOffice implements ShouldQueue
{
    use Queueable;

    public array $office;

    /**
     * Create a new job instance.
     */
    public function __construct(array $office)
    {
        $this->office = $office;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        Area::firstOrCreate(
            [
                'area' => $this->office['Name'],
                'pincode' => $this->office['Pincode'],
            ]
        );
    }
}
