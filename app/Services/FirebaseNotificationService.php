<?php

namespace App\Services;

use App\Models\User;
use Kreait\Firebase\Factory;
use Kreait\Firebase\Messaging\CloudMessage;
use Kreait\Firebase\Messaging\Notification;
use Illuminate\Support\Facades\Log;

class FirebaseNotificationService
{
    protected $messaging;

    public function __construct()
    {
        try {
            $firebaseCredentialsPath = storage_path('firebase_credentials.json');

            $factory = (new Factory)->withServiceAccount($firebaseCredentialsPath);
            $this->messaging = $factory->createMessaging();
        } catch (\Exception $e) {
            Log::error('Firebase initialization error: ' . $e->getMessage());
            throw $e;
        }
    }

    public function sendNotification($token, $title, $body, $data = [])
    {
        try {
            $data = collect($data)->map(function ($value) {
                if (is_array($value)) {
                    return json_encode($value); // flatten nested arrays
                }
                if ($value instanceof \Carbon\Carbon) {
                    return $value->toDateTimeString(); // format dates
                }
                return (string) $value; // cast everything else to string
            })->toArray();
            // Merge title and body into data (since we won't use Notification::create)
            $data = array_merge([
                'title' => $title,
                'body' => $body,
                'sound' => 'custom_sound'
            ], $data);

            $message = CloudMessage::withTarget('token', $token)
                ->withData($data) // ✅ Only data payload
                ->withHighestPossiblePriority();

            $result = $this->messaging->send($message);
            return $result;
        } catch (\Exception $e) {
            Log::error('Firebase notification error: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }


    public function sendMultipleNotifications($tokens, $title, $body, $data = [])
    {
        try {

            $results = [];
            foreach ($tokens as $token) {
                $results[] = $this->sendNotification($token, $title, $body, $data);
            }

            return $results;
        } catch (\Exception $e) {
            Log::error('Multiple notifications error: ' . $e->getMessage());
            throw $e;
        }
    }
}
