<?php

use App\Http\Controllers\AppointmentController;
use App\Http\Controllers\AppointmentStudentController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\GoogleOAuthController;
use App\Http\Controllers\MediaController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\OnboardingCompanyInfoController;
use App\Http\Controllers\OnboardingController;
use App\Http\Controllers\OnboardingLessonController;
use App\Http\Controllers\OnboardingPolicyController;
use App\Http\Controllers\OnboardingSystemAnalysisController;
use App\Http\Controllers\SystemController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Redis;


Route::prefix('v1')->group(function () {

    Route::get('/healthcheck', function () {
        $status = [
            'status'      => 'OK',
            'php_version' => phpversion(),
            'database'    => ['status' => 'unknown'],
            'redis'       => ['status' => 'unknown'],
        ];

        $httpCode = 200;

        try {
            DB::connection()->getPdo();
            $status['database'] = [
                'status'   => 'connected',
                'database' => DB::connection()->getDatabaseName(),
            ];
        } catch (\Exception $e) {
            $status['database'] = ['status' => 'error', 'message' => 'Database connection failed'];
            $httpCode = 500;
        }

        try {
            Redis::ping();
            $status['redis'] = ['status' => 'connected'];
        } catch (\Exception $e) {
            $status['redis'] = ['status' => 'error', 'message' => 'Redis connection failed'];
            $httpCode = 500;
        }

        $status['status']  = $httpCode === 500 ? 'ERROR' : 'OK';
        $status['message'] = $httpCode === 500
            ? 'Some services are down'
            : 'Customer Onboarding Management API is running smoothly.';

        return response()->json($status, $httpCode);
    });

    Route::prefix('auth')->middleware(['throttle:auth'])->group(function () {
        Route::post('/register', [AuthController::class, 'register']);
        Route::post('/login', [AuthController::class, 'login']);
        Route::post('/verify-otp', [AuthController::class, 'verifyOtp']);
    });

    Route::post('/auth/refresh-token', [AuthController::class, 'refreshToken'])
        ->middleware(['throttle:auth_refresh']);

    Route::get('/google/callback', [GoogleOAuthController::class, 'callback']);

    Route::middleware(['jwt.auth', 'throttle:api'])->group(function () {

        Route::post('/auth/logout', [AuthController::class, 'logout']);

        Route::get('/get-profile', [UserController::class, 'getProfile']);

        Route::get('/google/redirect', [GoogleOAuthController::class, 'redirect']);
        Route::get('/google/status', [GoogleOAuthController::class, 'status']);
        Route::delete('/google/disconnect', [GoogleOAuthController::class, 'disconnect']);

        Route::middleware(['role:admin'])->group(function () {
            Route::get('/user-detail/{userId}', [UserController::class, 'getUserById']);
            Route::get('/get-users', [UserController::class, 'listUsers']);
            Route::post('/create-user', [UserController::class, 'createUser']);
            Route::patch('/update-user-information/{userId}', [UserController::class, 'updateUserInformation']);
            Route::delete('/soft-delete-user/{userId}', [UserController::class, 'softDeleteUser']);
            Route::delete('/hard-delete-user/{userId}', [UserController::class, 'hardDeleteUser']);
            Route::patch('/restore-user/{userId}', [UserController::class, 'restoreUser']);
        });


        Route::middleware(['role:admin,sale,trainer'])->group(function () {
            Route::prefix('selection')->group(function () {
                Route::get('/trainers-dropdown', [UserController::class, 'listTrainers']);
                Route::get('/clients-dropdown', [UserController::class, 'listClients']);
            });
        });

        Route::middleware(['role:sale,trainer'])->group(function () {
            Route::post('/media', [MediaController::class, 'upload'])->middleware('throttle:media_upload');
        });

        Route::prefix('notifications')->group(function () {
            Route::get('/', [NotificationController::class, 'index']);
            Route::get('/unread-count', [NotificationController::class, 'unreadCount']);
            Route::patch('/{id}/read', [NotificationController::class, 'markRead']);
            Route::patch('/read-all', [NotificationController::class, 'markAllRead']);
        });

        Route::prefix('appointments')->group(function () {
            Route::get('/', [AppointmentController::class, 'index'])->name('appointments.index');
            Route::post('/', [AppointmentController::class, 'store'])->name('appointments.store');
            Route::get('/{id}', [AppointmentController::class, 'show'])->name('appointments.show');
            Route::patch('/{id}', [AppointmentController::class, 'update'])->name('appointments.update');
            Route::patch('/{id}/leave-office', [AppointmentController::class, 'leaveOffice'])->name('appointments.leaveOffice');
            Route::patch('/{id}/start', [AppointmentController::class, 'start'])->name('appointments.start');
            Route::patch('/{id}/complete', [AppointmentController::class, 'complete'])->name('appointments.complete');
            Route::post('/{id}/cancel', [AppointmentController::class, 'cancel'])->name('appointments.cancel');
            Route::post('/{id}/reschedule', [AppointmentController::class, 'reschedule'])->name('appointments.reschedule');

            Route::get('/{id}/students', [AppointmentStudentController::class, 'index'])->name('appointments.students.index');
            Route::post('/{id}/students', [AppointmentStudentController::class, 'store'])->name('appointments.students.store');
            Route::patch('/{id}/students/{sid}/attendance', [AppointmentStudentController::class, 'markAttendance'])->name('appointments.students.attendance');
        });

        Route::prefix('onboarding')->group(function () {
            Route::get('/', [OnboardingController::class, 'index'])->name('onboarding.index');
            Route::get('/{id}', [OnboardingController::class, 'show'])->name('onboarding.show');

            Route::post('/{id}/refresh-progress', [OnboardingController::class, 'refreshProgress'])
                ->middleware('throttle:onboarding_refresh')
                ->name('onboarding.refreshProgress');

            Route::patch('/{id}/complete', [OnboardingController::class, 'complete'])->name('onboarding.complete');
            Route::post('/{id}/cancel', [OnboardingController::class, 'cancel'])->name('onboarding.cancel');

            Route::get('/{id}/company-info', [OnboardingCompanyInfoController::class, 'show'])->name('onboarding.companyInfo.show');
            Route::patch('/{id}/company-info', [OnboardingCompanyInfoController::class, 'update'])->name('onboarding.companyInfo.update');

            Route::get('/{id}/system-analysis', [OnboardingSystemAnalysisController::class, 'show'])->name('onboarding.systemAnalysis.show');
            Route::patch('/{id}/system-analysis', [OnboardingSystemAnalysisController::class, 'update'])->name('onboarding.systemAnalysis.update');

            Route::get('/{id}/policies', [OnboardingPolicyController::class, 'index'])->name('onboarding.policies.index');
            Route::post('/{id}/policies', [OnboardingPolicyController::class, 'store'])->name('onboarding.policies.store');
            Route::patch('/{id}/policies/{pid}/check', [OnboardingPolicyController::class, 'check'])->name('onboarding.policies.check');
            Route::delete('/{id}/policies/{pid}', [OnboardingPolicyController::class, 'destroy'])->name('onboarding.policies.destroy');

            Route::get('/{id}/lessons', [OnboardingLessonController::class, 'index'])->name('onboarding.lessons.index');
            Route::post('/{id}/lessons', [OnboardingLessonController::class, 'store'])->name('onboarding.lessons.store');
            Route::patch('/{id}/lessons/{lid}', [OnboardingLessonController::class, 'update'])->name('onboarding.lessons.update');
            Route::delete('/{id}/lessons/{lid}', [OnboardingLessonController::class, 'destroy'])->name('onboarding.lessons.destroy');

            Route::post('/{id}/lessons/{lid}/send', [OnboardingLessonController::class, 'send'])
                ->middleware('throttle:lesson_send')
                ->name('onboarding.lessons.send');
        });

        Route::prefix('dashboard')->group(function () {
            Route::get('/trainer', [DashboardController::class, 'trainerDashboard'])->name('dashboard.trainer');
            Route::get('/sale', [DashboardController::class, 'saleDashboard'])->name('dashboard.sale');
        });

    });
});
