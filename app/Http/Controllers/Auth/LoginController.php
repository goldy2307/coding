<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Foundation\Auth\AuthenticatesUsers;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\AttendanceLog;



class LoginController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | Login Controller
    |--------------------------------------------------------------------------
    |
    | This controller handles authenticating users for the application and
    | redirecting them to your home screen. The controller uses a trait
    | to conveniently provide its functionality to your applications.
    |
    */

    use AuthenticatesUsers;

    /**
     * Where to redirect users after login.
     *
     * @var string
     */
    protected $redirectTo = '/home';

    protected array $middleware = [
        'guest' => ['except' => ['logout']],
        'auth' => ['only' => ['logout']],
    ];

    public function showLoginForm()
    {
        if (Auth::check()) {
            return redirect()->route('index'); // or 'home', depending on your route
        }

        return view('auth.login');
    }

    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        // $this->middleware('guest')->except('logout');
        // $this->middleware('auth')->only('logout');
    }

    protected function authenticated($request, $user)
    {
        $today = now()->toDateString();

        // Check if already punched in without punch_out today
        $existing = AttendanceLog::where('user_id', $user->id)
            ->where('date', $today)
            ->whereNull('punch_out')
            ->first();

        if (!$existing) {
            AttendanceLog::create([
                'user_id'  => $user->id,
                'punch_in' => now(),
                'date'     => $today,
            ]);
        }

        // Update user status
        $user->update([
            'is_logged_in' => true,
            'last_active'  => now(),
        ]);
    }

    public function logout(Request $request)
    {
        $user = Auth::user();

        if ($user) {
            $today = now()->toDateString();

            $lastLog = AttendanceLog::where('user_id', $user->id)
                ->where('date', $today)
                ->whereNull('punch_out')
                ->latest('punch_in')
                ->first();

            if ($lastLog) {
                $lastLog->update(['punch_out' => now()]);
            }

            $user->update(['is_logged_in' => false]);
        }

        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/login');
    }
}
