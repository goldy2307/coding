<?php

namespace App\Providers;

use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use App\Models\NotificationUser;

class ViewServiceProvider extends ServiceProvider
{
    public function boot()
    {
        View::composer('index-main', function ($view) {
            $notifications = NotificationUser::with('notification.sender')->where('user_id', auth()->id())
                ->where('is_read', 0)
                ->latest()
                ->get();

            $view->with('headerNotifications', $notifications);
        });
    }
}

