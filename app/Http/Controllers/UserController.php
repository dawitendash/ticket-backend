<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Services\UserService;
use App\Http\Resources\UserResource;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    public function __construct(
        protected UserService $service
    ) {}

    /**
     * Display a listing of users.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $users = $this->service->listUsers(   
            $request->all(),
            $request->integer('per_page', 15)
        );
        return UserResource::collection($users);
    }

    /**
     * Store a newly created user.
     */
    public function store(StoreUserRequest $request): JsonResponse
    {
        $user = $this->service->createUser($request->validated());  

        return (new UserResource($user->load('role')))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Display the specified user.
     */
    public function show(User $user): UserResource
    {
        return new UserResource($user->load('role'));
    }

    /**
     * Update the specified user.
     */
    public function update(UpdateUserRequest $request, User $user): UserResource
    {
        $updatedUser = $this->service->updateUser($user, $request->validated()); 

        return new UserResource($updatedUser->load('role'));
    }

    /**
     * Update the authenticated user's own profile.
     */
    public function updateProfile(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $data = $request->validate([
            'user_name' => ['sometimes', 'string', 'max:255', Rule::unique('users', 'user_name')->ignore($user->user_id, 'user_id')],
            'profile_name' => ['sometimes', 'array'],
            'profile_name.en' => ['nullable', 'string', 'max:255'],
            'profile_name.am' => ['nullable', 'string', 'max:255'],
            'email' => ['sometimes', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->user_id, 'user_id')],
            'phone_number' => ['nullable', 'string', 'max:255'],
            'profile_picture' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'profile_picture_url' => ['prohibited'],
            'lang' => ['nullable', 'string', 'max:10'],
        ]);

        if ($request->hasFile('profile_picture')) {
            if ($user->profile_picture_url && ! filter_var($user->profile_picture_url, FILTER_VALIDATE_URL)) {
                Storage::disk('public')->delete($user->profile_picture_url);
            }

            $data['profile_picture_url'] = $request->file('profile_picture')->store('users/profile-pictures', 'public');
            unset($data['profile_picture']);
        }

        $updatedUser = $this->service->updateUser($user, $data);  

        return response()->json([
            'data' => new \App\Http\Resources\AuthenticatedUserResource($updatedUser),
            'message' => 'Profile updated successfully.',
        ]);
    }

    /**
     * Remove the specified user.
     */
    public function destroy(User $user): JsonResponse
    {
        $this->service->deleteUser($user); 

        return response()->json(null, 204);
    }
    
    /**
     * Switch the current service provider context for the authenticated user.
     */
    public function switchContext(Request $request): JsonResponse
    {
        $request->validate([
            'service_provider_id' => 'required|uuid|exists:hospitality_service_providers,service_provider_id',
        ]);

        $user = $request->user();
        $newProviderId = $request->service_provider_id;

        // Verify user actually has an active role in this provider
        if (!$user->hasActiveRoleInProvider($newProviderId)) {
            return response()->json([
                'message' => 'You do not have any active role in this service provider.'
            ], 403);
        }

        session(['current_service_provider_id' => $newProviderId]);

        return response()->json([
            'current_service_provider' => $newProviderId,
            'permissions' => $user->getAllPermissions($newProviderId),
            'modules' => $user->getAccessibleModules($newProviderId),
            'message' => 'Service provider context switched successfully.'
        ]);
    }
}