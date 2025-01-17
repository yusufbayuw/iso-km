<?php

namespace App\Observers;

use App\Models\M003Document;

class M003DocumentObserver
{
    /**
     * Handle the M003Document "created" event.
     */
    public function created(M003Document $m003Document): void
    {
        //
    }

    /**
     * Handle the M003Document "updated" event.
     */
    public function updated(M003Document $m003Document): void
    {
        //
    }

    /**
     * Handle the M003Document "deleted" event.
     */
    public function deleted(M003Document $m003Document): void
    {
        //
    }

    /**
     * Handle the M003Document "restored" event.
     */
    public function restored(M003Document $m003Document): void
    {
        //
    }

    /**
     * Handle the M003Document "force deleted" event.
     */
    public function forceDeleted(M003Document $m003Document): void
    {
        //
    }
}
