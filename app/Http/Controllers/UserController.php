<?php

namespace App\Http\Controllers;

use App\Http\Requests\AdminCreateUserRequest;
use App\Http\Requests\UpdateUserInformationRequest;
use App\Services\UserService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserController extends Controller
{
    public function __construct(
        private UserService $userService
    ) {}

    /**
     * Get logged-in user profile
     */
    public function getProfile(Request $request): JsonResponse
    {
        $userId = $request->get('auth_user_id');
        $profile = $this->userService->getUserProfile($userId);

        return response()->json([
            'success' => true,
            'data' => $profile,
        ]);
    }

    /**
     * Get user by Id
     */
    public function getUserById(string $userId): JsonResponse
    {
        $user = $this->userService->getUserProfile($userId);

        return response()->json([
            'success' => true,
            'data' => $user,
        ]);
    }

    /**
     * Get list of all users - can be filtered by role or status
     */
    public function listUsers(Request $request): JsonResponse
    {
        $filters = $request->only([
            'role', 'gender', 'is_suspended', 'nationality', 'search',
            'with_trashed', 'only_trashed', 'sort_by', 'sort_order',
            'per_page', 'page',
        ]);

        $result = $this->userService->listUsers($filters);

        return response()->json([
            'success' => true,
            'data' => $result['data'],
            'pagination' => $result['pagination'],
        ]);
    }


    public function listClients(Request $request): JsonResponse
    {
        $filters = $request->only(['search', 'limit']);

        $clients = $this->userService->listClients($filters);

        return response()->json([
            'success' => true,
            'data' => $clients,
        ]);
    }

    public function listTrainers(Request $request): JsonResponse
    {
        $filters = $request->only([
            'search', 'sort_by', 'sort_order', 'limit',
        ]);

        $trainers = $this->userService->listTrainers($filters);

        return response()->json([
        'success' => true,
            'data' => $trainers,
        ]);
    }


    /**
     * Create new user (admin only - can create users with any role)
     */
    public function createUser(AdminCreateUserRequest $request): JsonResponse
    {
        $userData = $request->only([
            'first_name', 'last_name', 'dob', 'address', 'gender', 'nationality', 'role',
        ]);

        $credentialData = $request->only([
            'email', 'username', 'phone_number', 'password',
        ]);

        $personalInfoData = [
            'professtional_photo' => $request->file('professtional_photo'),
            'nationality_card' => $request->file('nationality_card'),
            'family_book' => $request->file('family_book'),
            'birth_certificate' => $request->file('birth_certificate'),
            'degreee_certificate' => $request->file('degreee_certificate'),
            'social_media' => $request->input('social_media'),
        ];

        $emergencyContactData = [
            'contact_first_name' => $request->input('contact_first_name'),
            'contact_last_name' => $request->input('contact_last_name'),
            'contact_relationship' => $request->input('contact_relationship'),
            'contact_phone_number' => $request->input('contact_phone_number'),
            'contact_address' => $request->input('contact_address'),
            'contact_social_media' => $request->input('contact_social_media'),
        ];

        $user = $this->userService->createUser(
            $userData,
            $credentialData,
            $personalInfoData,
            $emergencyContactData
        );

        return response()->json([
            'success' => true,
            'message' => 'User created successfully. OTP sent to email.',
            'data' => [
                'user_id' => $user->id,
                'email' => $user->credential->email,
            ],
        ], 201);
    }

    /**
     * Soft delete user (admin only)
     */
    public function softDeleteUser(string $userId): JsonResponse
    {
        $this->userService->softDeleteUser($userId);

        return response()->json([
            'success' => true,
            'message' => 'User soft deleted successfully',
        ]);
    }

    /**
     * Hard delete user permanently (admin only)
     */
    public function hardDeleteUser(string $userId): JsonResponse
    {
        $this->userService->hardDeleteUser($userId);

        return response()->json([
            'success' => true,
            'message' => 'User permanently deleted',
        ]);
    }

    /**
     * Restore soft deleted user (admin only)
     */
    public function restoreUser(string $userId): JsonResponse
    {
        $user = $this->userService->restoreUser($userId);

        return response()->json([
            'success' => true,
            'message' => 'User restored successfully',
            'data' => [
                'user_id' => $user->id,
                'email' => $user->credential->email,
            ],
        ]);
    }

    /**
     * Update user information (admin only)
     */
    public function updateUserInformation(UpdateUserInformationRequest $request, string $userId): JsonResponse
    {
        $userData = $request->only([
            'first_name', 'last_name', 'dob', 'address', 'gender', 'nationality',
        ]);

        $personalInfoData = [];
        if ($request->hasFile('professtional_photo') || $request->hasFile('nationality_card') ||
            $request->hasFile('family_book') || $request->hasFile('birth_certificate') ||
            $request->hasFile('degreee_certificate') || $request->has('social_media')) {

            $personalInfoData = [
                'professtional_photo' => $request->file('professtional_photo'),
                'nationality_card' => $request->file('nationality_card'),
                'family_book' => $request->file('family_book'),
                'birth_certificate' => $request->file('birth_certificate'),
                'degreee_certificate' => $request->file('degreee_certificate'),
                'social_media' => $request->input('social_media'),
            ];
        }

        $emergencyContactData = [];
        if ($request->has('contact_first_name') || $request->has('contact_last_name') ||
            $request->has('contact_relationship') || $request->has('contact_phone_number') ||
            $request->has('contact_address') || $request->has('contact_social_media')) {

            $emergencyContactData = $request->only([
                'contact_first_name', 'contact_last_name', 'contact_relationship',
                'contact_phone_number', 'contact_address', 'contact_social_media',
            ]);
        }

        $user = $this->userService->updateUserInformation(
            $userId,
            $userData,
            $personalInfoData,
            $emergencyContactData
        );

        return response()->json([
            'success' => true,
            'message' => 'User information updated successfully',
            'data' => [
                'user_id' => $user->id,
            ],
        ]);
    }
}
