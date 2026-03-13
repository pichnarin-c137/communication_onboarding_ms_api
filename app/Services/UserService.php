<?php

namespace App\Services;

use App\Exceptions\RoleNotFoundException;
use App\Exceptions\UserNotFoundException;
use App\Models\Client;
use App\Models\Credential;
use App\Models\EmergencyContact;
use App\Models\PersonalInformation;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class UserService
{
    private array $uploadedFiles = [];

    public function __construct(
        private OtpService $otpService
    ) {}

    public function createUser(
        array $userData,
        array $credentialData,
        array $personalInfoData = [],
        array $emergencyContactData = []
    ): User {
        // Reset uploaded files tracker
        $this->uploadedFiles = [];

        try {
            $user = DB::transaction(function () use ($userData, $credentialData, $personalInfoData, $emergencyContactData) {
                // Get role
                $role = Role::where('role', $userData['role'] ?? 'user')->first();

                if (! $role) {
                    throw new RoleNotFoundException("Role '{$userData['role']}' not found");
                }

                // Create user
                $user = User::create([
                    'role_id' => $role->id,
                    'first_name' => $userData['first_name'],
                    'last_name' => $userData['last_name'],
                    'dob' => $userData['dob'],
                    'address' => $userData['address'],
                    'gender' => $userData['gender'],
                    'nationality' => $userData['nationality'],
                ]);

                // Create credentials
                $credential = Credential::create([
                    'user_id' => $user->id,
                    'email' => $credentialData['email'],
                    'username' => $credentialData['username'],
                    'phone_number' => $credentialData['phone_number'],
                    'password' => Hash::make($credentialData['password']),
                ]);

                // Create personal information with image uploads
                if (! empty($personalInfoData)) {
                    $this->createPersonalInformation($user->id, $personalInfoData);
                }

                // Create emergency contact
                if (! empty($emergencyContactData)) {
                    $this->createEmergencyContact($user->id, $emergencyContactData);
                }

                return $user->load(['role', 'credential', 'personalInformation', 'emergencyContact']);
            });

            // Transaction succeeded, clear the tracker
            $this->uploadedFiles = [];

            // Send OTP AFTER transaction commits
            // This prevents email failures from rolling back user creation
            try {
                $this->otpService->sendOtp($user->credential);
            } catch (\Throwable $e) {
                // Log email failure but don't fail the user creation
                Log::warning('Failed to send OTP email after user creation', [
                    'user_id' => $user->id,
                    'email' => $user->credential->email,
                    'error' => $e->getMessage(),
                ]);
            }

            return $user;
        } catch (\Throwable $e) {
            // Transaction failed, clean up uploaded files
            $this->cleanupUploadedFiles();
            throw $e;
        }
    }

    private function createPersonalInformation(string $userId, array $data): PersonalInformation
    {
        Log::info('Creating personal information for user', ['user_id' => $userId, 'has_files' => [
            'professtional_photo' => isset($data['professtional_photo']) && $data['professtional_photo'] instanceof UploadedFile,
            'nationality_card' => isset($data['nationality_card']) && $data['nationality_card'] instanceof UploadedFile,
            'family_book' => isset($data['family_book']) && $data['family_book'] instanceof UploadedFile,
            'birth_certificate' => isset($data['birth_certificate']) && $data['birth_certificate'] instanceof UploadedFile,
            'degreee_certificate' => isset($data['degreee_certificate']) && $data['degreee_certificate'] instanceof UploadedFile,
        ]]);

        $personalInfoData = [
            'user_id' => $userId,
            'professtional_photo' => $this->uploadDocument($data['professtional_photo'] ?? null, 'professtional_photos'),
            'nationality_card' => $this->uploadDocument($data['nationality_card'] ?? null, 'nationality_cards'),
            'family_book' => $this->uploadDocument($data['family_book'] ?? null, 'family_books'),
            'birth_certificate' => $this->uploadDocument($data['birth_certificate'] ?? null, 'birth_certificates'),
            'degreee_certificate' => $this->uploadDocument($data['degreee_certificate'] ?? null, 'degree_certificates'),
            'social_media' => $data['social_media'] ?? null,
        ];

        Log::info('Personal information paths after upload', [
            'user_id' => $userId,
            'paths' => $personalInfoData,
        ]);

        return PersonalInformation::create($personalInfoData);
    }

    private function createEmergencyContact(string $userId, array $data): EmergencyContact
    {
        return EmergencyContact::create([
            'user_id' => $userId,
            'contact_first_name' => $data['contact_first_name'],
            'contact_last_name' => $data['contact_last_name'],
            'contact_relationship' => $data['contact_relationship'],
            'contact_phone_number' => $data['contact_phone_number'],
            'contact_address' => $data['contact_address'],
            'contact_social_media' => $data['contact_social_media'] ?? null,
        ]);
    }

    private function uploadDocument(?UploadedFile $file, string $folder): ?string
    {
        if (! $file) {
            Log::debug('No file provided for upload', ['folder' => $folder]);

            return null;
        }

        if (! $file->isValid()) {
            Log::warning('Invalid file upload attempted', [
                'folder' => $folder,
                'error' => $file->getErrorMessage(),
                'original_name' => $file->getClientOriginalName(),
            ]);

            return null;
        }

        Log::info('Attempting to upload file', [
            'folder' => $folder,
            'original_name' => $file->getClientOriginalName(),
            'size' => $file->getSize(),
            'mime_type' => $file->getMimeType(),
        ]);

        try {
            // Store in storage/app/public/documents/{folder}
            $path = $file->store("documents/{$folder}", 'public');

            if ($path) {
                $this->uploadedFiles[] = $path;
                $fullPath = Storage::disk('public')->path($path);
                $exists = Storage::disk('public')->exists($path);

                Log::info('File uploaded successfully', [
                    'path' => $path,
                    'full_path' => $fullPath,
                    'exists_after_upload' => $exists,
                ]);
            } else {
                Log::error('File upload returned empty path', [
                    'folder' => $folder,
                    'original_name' => $file->getClientOriginalName(),
                ]);
            }

            return $path;
        } catch (\Throwable $e) {
            Log::error('File upload failed with exception', [
                'folder' => $folder,
                'original_name' => $file->getClientOriginalName(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        }
    }

    private function cleanupUploadedFiles(): void
    {
        foreach ($this->uploadedFiles as $path) {
            if (Storage::disk('public')->exists($path)) {
                Storage::disk('public')->delete($path);
            }
        }

        // Clear the tracker
        $this->uploadedFiles = [];
    }

    public function listUsers(array $filters = []): array
    {
        $query = User::with(['role', 'credential', 'personalInformation', 'emergencyContact']);

        // Only include users that have credentials (incomplete users without credentials are invalid)
        $query->whereHas('credential');

        // Apply filters
        if (! empty($filters['role'])) {
            $query->whereHas('role', function ($q) use ($filters) {
                $q->where('role', $filters['role']);
            });
        }

        if (! empty($filters['gender'])) {
            $query->where('gender', $filters['gender']);
        }

        if (! empty($filters['is_suspended'])) {
            $query->where('is_suspended', filter_var($filters['is_suspended'], FILTER_VALIDATE_BOOLEAN));
        }

        if (! empty($filters['nationality'])) {
            $query->where('nationality', 'like', '%'.$filters['nationality'].'%');
        }

        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('first_name', 'like', '%'.$search.'%')
                    ->orWhere('last_name', 'like', '%'.$search.'%')
                    ->orWhereHas('credential', function ($cq) use ($search) {
                        $cq->where('email', 'like', '%'.$search.'%')
                            ->orWhere('username', 'like', '%'.$search.'%');
                    });
            });
        }

        // Include or exclude soft deleted users
        if (! empty($filters['with_trashed']) && $filters['with_trashed'] === 'true') {
            $query->withTrashed();
        } elseif (! empty($filters['only_trashed']) && $filters['only_trashed'] === 'true') {
            $query->onlyTrashed();
        }

        // Sorting
        $sortBy = $filters['sort_by'] ?? 'created_at';
        $sortOrder = $filters['sort_order'] ?? 'desc';
        $query->orderBy($sortBy, $sortOrder);

        // Pagination
        $perPage = (int) ($filters['per_page'] ?? 15);
        $page = (int) ($filters['page'] ?? 1);

        $paginated = $query->paginate($perPage, ['*'], 'page', $page);

        return [
            'data' => $paginated->getCollection()->map(function ($user) {
                return [
                    'id' => $user->id,
                    'first_name' => $user->first_name,
                    'last_name' => $user->last_name,
                    'full_name' => $user->full_name,
                    'dob' => $user->dob->format('Y-m-d'),
                    'address' => $user->address,
                    'gender' => $user->gender,
                    'nationality' => $user->nationality,
                    'is_suspended' => $user->is_suspended,
                    'role' => $user->role->role,
                    'email' => $user->credential->email,
                    'username' => $user->credential->username,
                    'phone_number' => $user->credential->phone_number,
                    'emergency_contact' => $user->emergencyContact ? [
                        'contact_first_name' => $user->emergencyContact->contact_first_name,
                        'contact_last_name' => $user->emergencyContact->contact_last_name,
                        'contact_full_name' => $user->emergencyContact->full_name,
                        'contact_relationship' => $user->emergencyContact->contact_relationship,
                        'contact_phone_number' => $user->emergencyContact->contact_phone_number,
                        'contact_address' => $user->emergencyContact->contact_address,
                        'contact_social_media' => $user->emergencyContact->contact_social_media,
                    ] : null,
                    'personal_information' => $user->personalInformation ? [
                        'professtional_photo' => $user->personalInformation->professtional_photo,
                        'professtional_photo_url' => $user->personalInformation->professtional_photo_url,
                        'nationality_card' => $user->personalInformation->nationality_card,
                        'nationality_card_url' => $user->personalInformation->nationality_card_url,
                        'family_book' => $user->personalInformation->family_book,
                        'family_book_url' => $user->personalInformation->family_book_url,
                        'birth_certificate' => $user->personalInformation->birth_certificate,
                        'birth_certificate_url' => $user->personalInformation->birth_certificate_url,
                        'degreee_certificate' => $user->personalInformation->degreee_certificate,
                        'degree_certificate_url' => $user->personalInformation->degree_certificate_url,
                        'social_media' => $user->personalInformation->social_media,
                    ] : null,
                    'created_at' => $user->created_at->toIso8601String(),
                    'deleted_at' => $user->deleted_at?->toIso8601String(),
                ];
            })->values()->toArray(),
            'pagination' => [
                'total' => $paginated->total(),
                'per_page' => $paginated->perPage(),
                'current_page' => $paginated->currentPage(),
                'last_page' => $paginated->lastPage(),
                'from' => $paginated->firstItem(),
                'to' => $paginated->lastItem(),
            ],
        ];
    }

    public function listClients(array $filters = []): array
    {
        $query = Client::query()
            ->select('id', 'company_name', 'company_code', 'phone_number');

        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $query->where('company_name', 'like', '%'.$search.'%');
            $query->orWhere('company_code', 'like', '%'.$search.'%');
            $query->orWhere('phone_number', 'like', '%'.$search.'%');
        }

        $sortBy = $filters['sort_by'] ?? 'company_name';
        $sortOrder = $filters['sort_order'] ?? 'asc';

        $allowedSortColumns = ['id', 'company_name', 'company_code', 'phone_number'];

        if (! in_array($sortBy, $allowedSortColumns)) {
            $sortBy = 'company_name';
        }

        $query->orderBy($sortBy, $sortOrder);

        $limit = isset($filters['limit']) ? (int) $filters['limit'] : 20;

        return $query->limit($limit)->get()->toArray();
    }

    public function listTrainers(array $filters = []): array
    {
        $query = User::with(['credential'])
            ->whereHas('role', fn ($q) => $q->where('role', 'trainer'))
            ->whereHas('credential');

        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('first_name', 'ilike', "%{$search}%")
                    ->orWhere('last_name', 'ilike', "%{$search}%")
                    ->orWhereHas('credential', function ($cq) use ($search) {
                        $cq->where('phone_number', 'ilike', "%{$search}%");
                    });
            });
        }

        $sortBy = $filters['sort_by'] ?? 'created_at';
        $sortOrder = $filters['sort_order'] ?? 'desc';
        $query->orderBy($sortBy, $sortOrder);

        if (empty($filters['search'])) {
            $query->limit(isset($filters['limit']) ? (int) $filters['limit'] : 10);
        }

        return $query->get(['id', 'first_name', 'last_name'])->toArray();
    }

    public function getUserProfile(string $userId): array
    {
        $user = User::with(['role', 'credential', 'personalInformation', 'emergencyContact'])->find($userId);

        if (! $user) {
            throw new UserNotFoundException('User not found', 0, null, ['user_id' => $userId]);
        }

        $profile = [
            'id' => $user->id,
            'first_name' => $user->first_name,
            'last_name' => $user->last_name,
            'full_name' => $user->full_name,
            'dob' => $user->dob->format('Y-m-d'),
            'address' => $user->address,
            'gender' => $user->gender,
            'nationality' => $user->nationality,
            'is_suspended' => $user->is_suspended,
            'role' => $user->role->role,
            'email' => $user->credential->email,
            'username' => $user->credential->username,
            'phone_number' => $user->credential->phone_number,
            'created_at' => $user->created_at->toIso8601String(),
        ];

        // Add personal information if exists
        if ($user->personalInformation) {
            $profile['personal_information'] = [
                'professtional_photo' => $user->personalInformation->professtional_photo,
                'professtional_photo_url' => $user->personalInformation->professtional_photo_url,
                'nationality_card' => $user->personalInformation->nationality_card,
                'nationality_card_url' => $user->personalInformation->nationality_card_url,
                'family_book' => $user->personalInformation->family_book,
                'family_book_url' => $user->personalInformation->family_book_url,
                'birth_certificate' => $user->personalInformation->birth_certificate,
                'birth_certificate_url' => $user->personalInformation->birth_certificate_url,
                'degreee_certificate' => $user->personalInformation->degreee_certificate,
                'degree_certificate_url' => $user->personalInformation->degree_certificate_url,
                'social_media' => $user->personalInformation->social_media,
            ];
        }

        // Add emergency contact if exists
        if ($user->emergencyContact) {
            $profile['emergency_contact'] = [
                'contact_first_name' => $user->emergencyContact->contact_first_name,
                'contact_last_name' => $user->emergencyContact->contact_last_name,
                'contact_full_name' => $user->emergencyContact->full_name,
                'contact_relationship' => $user->emergencyContact->contact_relationship,
                'contact_phone_number' => $user->emergencyContact->contact_phone_number,
                'contact_address' => $user->emergencyContact->contact_address,
                'contact_social_media' => $user->emergencyContact->contact_social_media,
            ];
        }

        return $profile;
    }

    public function toggleSuspension(string $userId): User
    {
        $user = User::find($userId);

        if (! $user) {
            throw new UserNotFoundException('User not found', 0, null, ['user_id' => $userId]);
        }

        $user->update(['is_suspended' => ! $user->is_suspended]);

        return $user;
    }

    public function softDeleteUser(string $userId): bool
    {
        $user = User::find($userId);

        if (! $user) {
            throw new UserNotFoundException('User not found', 0, null, ['user_id' => $userId]);
        }

        return $user->delete();
    }

    public function hardDeleteUser(string $userId): bool
    {
        $user = User::withTrashed()->find($userId);

        if (! $user) {
            throw new UserNotFoundException('User not found', 0, null, ['user_id' => $userId]);
        }

        // Delete related images before hard deleting
        if ($user->personalInformation) {
            $this->deletePersonalInformationFiles($user->personalInformation);
        }

        return $user->forceDelete();
    }

    public function restoreUser(string $userId): User
    {
        $user = User::withTrashed()->find($userId);

        if (! $user) {
            throw new UserNotFoundException('User not found', 0, null, ['user_id' => $userId]);
        }

        if (! $user->trashed()) {
            throw new \InvalidArgumentException('User is not soft deleted');
        }

        $user->restore();

        return $user->load(['role', 'credential']);
    }

    public function updateUserInformation(string $userId, array $userData, array $personalInfoData = [], array $emergencyContactData = []): User
    {
        $this->uploadedFiles = [];

        try {
            return DB::transaction(function () use ($userId, $userData, $personalInfoData, $emergencyContactData) {
                $user = User::find($userId);

                if (! $user) {
                    throw new UserNotFoundException('User not found', 0, null, ['user_id' => $userId]);
                }

                // Update basic user information
                if (! empty($userData)) {
                    $user->update($userData);
                }

                // Update personal information
                if (! empty($personalInfoData)) {
                    $this->updatePersonalInformation($user, $personalInfoData);
                }

                // Update emergency contact
                if (! empty($emergencyContactData)) {
                    $this->updateEmergencyContact($user, $emergencyContactData);
                }

                return $user->fresh(['role', 'credential', 'personalInformation', 'emergencyContact']);
            });

            // Clear tracker on success
            $this->uploadedFiles = [];
        } catch (\Throwable $e) {
            // Clean up uploaded files on failure
            $this->cleanupUploadedFiles();
            throw $e;
        }
    }

    private function updatePersonalInformation(User $user, array $data): void
    {
        $personalInfo = $user->personalInformation;

        if (! $personalInfo) {
            // Create if doesn't exist
            $this->createPersonalInformation($user->id, $data);

            return;
        }

        $updateData = [];

        // Handle image uploads
        if (isset($data['professtional_photo']) && $data['professtional_photo'] instanceof UploadedFile) {
            // Delete old file if exists
            if ($personalInfo->professtional_photo) {
                Storage::disk('public')->delete($personalInfo->professtional_photo);
            }
            $updateData['professtional_photo'] = $this->uploadDocument($data['professtional_photo'], 'professtional_photos');
        }

        if (isset($data['nationality_card']) && $data['nationality_card'] instanceof UploadedFile) {
            if ($personalInfo->nationality_card) {
                Storage::disk('public')->delete($personalInfo->nationality_card);
            }
            $updateData['nationality_card'] = $this->uploadDocument($data['nationality_card'], 'nationality_cards');
        }

        if (isset($data['family_book']) && $data['family_book'] instanceof UploadedFile) {
            if ($personalInfo->family_book) {
                Storage::disk('public')->delete($personalInfo->family_book);
            }
            $updateData['family_book'] = $this->uploadDocument($data['family_book'], 'family_books');
        }

        if (isset($data['birth_certificate']) && $data['birth_certificate'] instanceof UploadedFile) {
            if ($personalInfo->birth_certificate) {
                Storage::disk('public')->delete($personalInfo->birth_certificate);
            }
            $updateData['birth_certificate'] = $this->uploadDocument($data['birth_certificate'], 'birth_certificates');
        }

        if (isset($data['degreee_certificate']) && $data['degreee_certificate'] instanceof UploadedFile) {
            if ($personalInfo->degreee_certificate) {
                Storage::disk('public')->delete($personalInfo->degreee_certificate);
            }
            $updateData['degreee_certificate'] = $this->uploadDocument($data['degreee_certificate'], 'degree_certificates');
        }

        if (isset($data['social_media'])) {
            $updateData['social_media'] = $data['social_media'];
        }

        if (! empty($updateData)) {
            $personalInfo->update($updateData);
        }
    }

    private function updateEmergencyContact(User $user, array $data): void
    {
        $emergencyContact = $user->emergencyContact;

        if (! $emergencyContact) {
            // Create if doesn't exist
            $this->createEmergencyContact($user->id, $data);

            return;
        }

        $emergencyContact->update($data);
    }

    private function deletePersonalInformationFiles(PersonalInformation $personalInfo): void
    {
        $files = [
            $personalInfo->professtional_photo,
            $personalInfo->nationality_card,
            $personalInfo->family_book,
            $personalInfo->birth_certificate,
            $personalInfo->degreee_certificate,
        ];

        foreach ($files as $file) {
            if ($file && Storage::disk('public')->exists($file)) {
                Storage::disk('public')->delete($file);
            }
        }
    }
}
