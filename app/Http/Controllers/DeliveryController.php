<?php

namespace App\Http\Controllers;

use App\Events\NotificationCreated;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use GuzzleHttp\Client;
use App\Models\User;
use GuzzleHttp\Cookie\CookieJar;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use App\Notifications\ForgotPasswordMail;
use Illuminate\Support\Facades\Password;
use App\Services\FirebaseNotificationService;
use App\Models\Notification;
use App\Models\NotificationUser;
use App\Models\SalesOrder;
use App\Models\SalesOrderTracking;
use App\Notifications\DeliveryStatusUpdate;

class DeliveryController extends Controller
{
    public function login(Request $request)
    {
        try {
            $request->validate([
                'email' => 'required|exists:users,email',
                'password' => 'required|string|min:6',
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'errors' => $e->errors(),
                'message' => 'Validation failed',
            ], 200);
        }

        $user = User::where('email', $request->email)->first();

        if (! $user) {
            return response()->json(['errors' => ['Invalid credentials'], 'message' => 'No user found with this email'], 200);
        } elseif (! Hash::check($request->password, $user->password)) {
            return response()->json(['errors' => ['Invalid credentials'], 'message' => 'Invalid password'], 200);
        } elseif (! $user->hasRole('delivery employee')) {
            return response()->json(['errors' => ['Unauthorized'], 'message' => 'User does not have delivery employee role'], 200);
        }
        
        $user->tokens()->delete();

        $token = $user->createToken('DeliveryApp')->plainTextToken;
        $user->auth_token = $token;
        $user->save();

        return response()->json([
            'token' => $token,
            'user' => $user,
        ]);
    }

    public function sendOtp(Request $request)
    {
        try {
            $request->validate([
                'phone_no' => 'required|numeric|digits:10',
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'errors' => $e->errors(),
                'message' => 'Validation failed',
            ], 200);
        }

        $user = User::where('phone', $request->phone_no)->first();
        if (! $user) {
            return response()->json([
                'errors' => ['User not found'],
                'message' => 'Provided number is not registered with any account.',
            ]);
        } elseif (! $user->hasRole('delivery employee')) {
            return response()->json(['errors' => ['Unauthorized'], 'message' => 'User does not have delivery employee role'], 200);
        }

        $jar = new CookieJar();


        $client = new Client();

        // Submit phone number to get OTP
        $response = $client->post('https://auth.phone.email/submit-login', [
            'form_params' => [
                'phone_no' => $request->phone_no,
                'phone_country' => '+91',
                'client_id' => config('app.phone_email.client_id'),
            ],
            'cookies' => $jar,
        ]);
        // Store the cookie jar for reuse
        file_put_contents('cookiejar.serialize', serialize($jar));
        $body = $response->getBody()->getContents();
        // Optionally decode if response is JSON
        $json = json_decode($body, true);

        return response()->json([
            'status' => true,
            'data' => $json ?? $body // fallback to raw body if not JSON
        ]);
    }

    public function verifyOtp(Request $request)
    {
        try {
            $request->validate([
                'otp' => 'required|numeric|digits:6',
                'phone_no' => 'required|numeric|digits:10',
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'errors' => $e->errors(),
                'message' => 'Validation failed',
            ], 200);
        }
        $jar = unserialize(file_get_contents('cookiejar.serialize'));

        $client = new Client();

        // Submit the OTP and verify login
        $response = $client->post('https://auth.phone.email/verify-login', [
            'form_params' => [
                'otp' => $request->otp,
                'client_id' => config('app.phone_email.client_id'),
                'fname' => 'Aayush',
                'lname' => 'Patidar',
            ],
            'cookies' => $jar,
        ]);

        $body = $response->getBody()->getContents();

        // Optionally decode if response is JSON
        $json = json_decode($body, true);
        if ($json['flag'] == 1) {
            $user = User::where('phone', $request->phone_no)->first();
            if ($user && $user->hasRole('delivery employee')) {
                $user->tokens()->delete();
                $token = $user->createToken('DeliveryApp')->plainTextToken;
                $user->auth_token = $token;
                $user->save();

                return response()->json([
                    'status' => true,
                    'verification' => true,
                    'data' => [
                        'user' => $user,
                        'token' => $token,
                        'message' => 'User verified and logged in successfully.',
                        'verification' => $json ?? $body,
                    ],
                ]);
            } else {
                return response()->json([
                    'status' => false,
                    'verification' => true,
                    'data' => [
                        'message' => 'User not found. Please contact HR.',
                        'verification' => $json ?? $body,
                    ],
                ]);
            }
        } else {
            return response()->json([
                'status' => false,
                'verfication' => false,
                'data' => $json ?? $body, // fallback to raw body if not JSON
            ]);
        }
    }

    public function storeDeviceToken(Request $request)
    {
        try {
            $request->validate([
                'device_token' => 'required|string',
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'errors' => $e->errors(),
                'message' => 'Validation failed',
            ], 200);
        }

        $user = Auth::user();
        $user->device_token = $request->device_token;
        $user->save();

        return response()->json([
            'status' => true,
            'message' => 'Device token stored successfully'
        ]);
    }

    public function logout(Request $request)
    {
        $user = $request->user();
        if ($user) {
            $user->tokens()->where('id', $user->currentAccessToken()->id)->delete();
            return response()->json(['message' => 'Logged out successfully']);
        }
        return response()->json(['errors' => ['User not authorized'], 'message' => 'Unauthorized'], 200);
    }

    public function forgot_password(Request $request)
    {
        try {
            $request->validate([
                'email' => 'required|email|exists:users,email',
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'errors' => $e->errors(),
                'message' => 'Validation failed',
            ], 200);
        }

        // Generate a password reset token and send it to the user's email
        $user = User::where('email', $request->email)->first();
        if (! $user) {
            return response()->json(['errors' => ['User not found'], 'message' => 'Please enter a valid registered email.'], 200);
        } elseif (! $user->hasRole('delivery employee')) {
            return response()->json(['errors' => ['User not authorized'], 'message' => 'You are not authorized to perform this action.'], 200);
        }

        $token = Password::createToken($user);
        $user->notify(new ForgotPasswordMail($token));
        return response()->json(['message' => 'Password reset email sent successfully']);
    }

    private function sendNotification($fcmToken, $title, $body, $data)
    {
        $firebase = new FirebaseNotificationService();
        try {
            $firebase->sendNotification($fcmToken, $title, $body, $data);
        } catch (\Throwable $e) {
            // Log::info($e->getMessage());
        }
    }


    public function notifications()
    {
        $notifications = NotificationUser::with('notification')
            ->where('user_id', Auth::id())
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'status' => true,
            'data' => $notifications,
        ]);
    }

    public function pendingDeliveries()
    {
        $user = Auth::user();
        if (! $user->hasRole('delivery employee')) {
            return response()->json(['errors' => ['Unauthorized'], 'message' => 'User does not have delivery employee role'], 200);
        }

        $pendingDeliveries = SalesOrder::where('delivery_employee_id', $user->id)
            ->where('delivery_status', 'pending')
            ->with(['shop', 'user', 'deliveryEmployee'])
            ->orderBy('expected_delivery_date', 'desc')
            ->get();

        return response()->json([
            'status' => true,
            'data' => $pendingDeliveries,
        ]);
    }

    public function allDeliveries()
    {
        $user = Auth::user();
        if (! $user->hasRole('delivery employee')) {
            return response()->json(['errors' => ['Unauthorized'], 'message' => 'User does not have delivery employee role'], 200);
        }

        $pendingDeliveries = SalesOrder::where('delivery_employee_id', $user->id)
            ->with(['shop', 'user', 'deliveryEmployee', 'latestTracking'])
            ->orderBy('expected_delivery_date', 'desc')
            ->get();

        return response()->json([
            'status' => true,
            'data' => $pendingDeliveries,
        ]);
    }

    public function updateDeliveryStatus(Request $request)
    {
        $user = Auth::user();
        if (! $user->hasRole('delivery employee')) {
            return response()->json(['errors' => ['Unauthorized'], 'message' => 'User does not have delivery employee role'], 200);
        }

        try {
            $request->validate([
                'order_id' => 'required|exists:sales_orders,id',
                'status' => ['required', Rule::in(['Picked up from facility - In Transit', 'In Transit', 'Out for Delivery', 'Delivered', 'Failed'])],
                'remarks' => 'required_if:status,Failed|string|max:255',
                'proof_of_delivery' => 'nullable|required_if:status,Delivered,Failed|image',
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'errors' => $e->errors(),
                'message' => 'Validation failed',
            ], 200);
        }

        if($request->hasFile('proof_of_delivery')){
            $file = $request->file('proof_of_delivery');
            $ext = $file->getClientOriginalExtension();
            $filename = time() . '.' . $ext;
            $file->move('ProofOfDelivery/', $filename);
            $proof = 'ProofOfDelivery/' . $filename;
        }else{
            $proof = null;
        }

        $order = SalesOrder::where('id', $request->order_id)
            ->where('delivery_employee_id', $user->id)
            ->first();

        if (! $order) {
            return response()->json(['errors' => ['Order not found or not assigned to you'], 'message' => 'Order not found or not assigned to you'], 200);
        }

        $tracking = SalesOrderTracking::create([
            'sales_order_id' => $order->id,
            'checkpoint' => $request->status,
            'remarks' => $request->remarks,
            'checkpoint_time' => now(),
            'proof_of_delivery' => $proof,
            'created_by' => $user->email,
            'last_updated_by' => $user->email
        ]);

        $creator = User::where('email', $order->created_by)->first();

        $notification = Notification::create([
            'type' => 'delivery_status',
            'title' => "Shipment marked as {$request->status}",
            'body' => "Shipment for Sales Order #{$order->order_id} has been updated to '{$request->status}' by " . Auth::user()->name,
            'data' => [
                'order_id' => $order->id,
                'link' => route('sales-order', $order->order_number),
            ],
            'sender_id' => Auth::id(),
        ]);

        $notification->users()->attach($creator->id, [
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        event(new NotificationCreated($notification, $creator->id));


        if (in_array($request->status, ['Delivered', 'Failed'])) {
            $order->checkpoint = $request->status;

            $usersToNotify = User::whereHas('roles', function ($query) {
                $query->where(function ($q) {
                    $q->where('name', 'like', '%logistics%')
                    ->orWhere('name', 'like', '%delivery%');
                })->where('name', '!=', 'delivery employee');
            })->get();

            $notification = Notification::create([
                'type' => 'delivery_status',
                'title' => "Shipment marked as {$request->status}",
                'body' => "Shipment for Sales Order #{$order->order_id} has been updated to '{$request->status}' by " . Auth::user()->name,
                'data' => [
                    'order_id' => $order->id,
                    'link' => route('sales-order', $order->order_number),
                ],
                'sender_id' => Auth::id(),
            ]);

            foreach ($usersToNotify as $item) {
                if (Auth::id() != $item->id) {
                    $notification->users()->attach($item->id, [
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);

                    event(new NotificationCreated($notification, $item->id));
                    // $item->notify(new DeliveryStatusUpdate($order, $request->status, $request->remarks));
                }
            }
        }

        return response()->json([
            'status' => true,
            'message' => 'Delivery status updated successfully',
            'data' => $order,
        ]);
    }


}
