<?php

namespace App;

use App\Models\ChangeLog;
use Illuminate\Support\Facades\Auth;

trait LogsModelChanges
{
    public static function bootLogsModelChanges()
    {
        static::updating(function ($model) {
            $original = $model->getOriginal();
            $changes = $model->getDirty();
            $userMail = Auth::check() ? Auth::user()->email : 'system@switchit.com';

            foreach ($changes as $column => $newValue) {
                ChangeLog::create([
                    'table_name'  => $model->getTable(),
                    'row_id'      => $model->id,
                    'column_name' => $column,
                    'old_value'   => is_array($original[$column]) ? json_encode($original[$column]) : $original[$column],
                    'new_value'   => is_array($newValue) ? json_encode($newValue) : $newValue,
                    'change_type' => 'updated',
                    'changed_by'  => $userMail,
                ]);
            }
        });

        static::deleting(function ($model) {
            $userMail = Auth::user()->email;

            // Only log soft deletes, not force deletes
            if (method_exists($model, 'isForceDeleting') && !$model->isForceDeleting()) {
                ChangeLog::create([
                    'table_name'  => $model->getTable(),
                    'row_id'      => $model->id,
                    'column_name' => 'deleted_at',
                    'old_value'   => null,
                    'new_value'   => now()->toDateTimeString(),
                    'change_type' => 'deleted',
                    'changed_by'  => $userMail,
                ]);
            } else {
                // Log full row before hard delete
                foreach ($model->getAttributes() as $column => $value) {
                    ChangeLog::create([
                        'table_name'  => $model->getTable(),
                        'row_id'      => $model->id,
                        'column_name' => $column,
                        'old_value'   => is_array($value) ? json_encode($value) : $value,
                        'new_value'   => null,
                        'change_type' => 'deleted',
                        'changed_by'  => $userMail,
                    ]);
                }
            }
        });
    }
}
