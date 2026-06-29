<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreUserInformationRequest;
use App\Http\Requests\UpdateUserInformationRequest;
use App\Http\Resources\UserInformationResource;
use App\Models\UserInformation;
use App\Services\UserInformationService;
use Illuminate\Http\Request;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

class UserInformationController extends Controller
{
    use AuthorizesRequests;
    
    protected UserInformationService $service;

    public function __construct(UserInformationService $service)
    {
        $this->service = $service;
    }

    // =============================================
    // PUBLIC ROUTES - Everyone can view
    // =============================================

    public function index(Request $request)
    {
        $this->authorize('viewAny', UserInformation::class);
        $filters = $request->only([
            'user_id', 'device_id', 'has_national_id', 'is_complete', 'search'
        ]);

        $perPage = $request->integer('per_page', 15);
        
        $informations = $this->service->listUserInformation($filters, $perPage);

        return response()->json([
            'success' => true,
            'data' => UserInformationResource::collection($informations),
        ]);
    }

    public function getByUserId(string $userId)
    {
        $information = $this->service->getByUserId($userId);

        if (!$information) {
            return response()->json([
                'success' => false,
                'message' => 'User information not found',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => new UserInformationResource($information),
        ]);
    }

    public function getByDeviceId(string $deviceId)
    {
        $information = $this->service->getByDeviceId($deviceId);

        if (!$information) {
            return response()->json([
                'success' => false,
                'message' => 'User information not found for this device',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => new UserInformationResource($information),
        ]);
    }

    public function getByPhoneNumber(Request $request)
    {
        $request->validate([
            'phone_number' => ['required', 'string'],
        ]);

        $information = $this->service->getByPhoneNumber($request->phone_number);

        if (!$information) {
            return response()->json([
                'success' => false,
                'message' => 'User information not found for this phone number',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => new UserInformationResource($information),
        ]);
    }

    public function show(string $id)
    {
        $this->authorize('view', UserInformation::class);
        
        try {
            $information = $this->service->findUserInformationOrFail($id);

            return response()->json([
                'success' => true,
                'data' => new UserInformationResource($information),
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'User information not found',
            ], 404);
        }
    }

    public function myInformation()
    {
        $user = auth()->user();
        
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated',
            ], 401);
        }

        $information = $this->service->getByUserId($user->user_id);

        if (!$information) {
            return response()->json([
                'success' => false,
                'message' => 'User information not found',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => new UserInformationResource($information),
        ]);
    }

    // =============================================
    // AUTHENTICATED ROUTES
    // =============================================

    public function store(StoreUserInformationRequest $request)
    {
        try {
            $data = $request->validated();
             
            if (!empty($data['user_id'])) {
                unset($data['device_id']);
            }
            
            if (empty($data['user_id']) && auth()->check()) {
                $data['user_id'] = auth()->id();
                unset($data['device_id']);  
            }
     
            if (empty($data['user_id']) && empty($data['device_id'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Either user_id or device_id is required.',
                ], 422);
            }
            
            $information = $this->service->createUserInformation($data);

            return response()->json([
                'success' => true,
                'message' => 'User information created successfully',
                'data' => new UserInformationResource($information),
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create user information: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function update(UpdateUserInformationRequest $request, string $id)
    {
        try {
            $information = $this->service->findUserInformationOrFail($id);
            $this->authorize('update', $information);

            $data = $request->validated();
            $information = $this->service->updateUserInformation($information, $data);

            return response()->json([
                'success' => true,
                'message' => 'User information updated successfully',
                'data' => new UserInformationResource($information),
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update user information: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function updateMyInformation(UpdateUserInformationRequest $request)
    {
        try {
            $user = auth()->user();
            $information = $this->service->getByUserId($user->user_id);
            
            if (!$information) {
                return response()->json([
                    'success' => false,
                    'message' => 'User information not found',
                ], 404);
            }

            $data = $request->validated();
            $information = $this->service->updateUserInformation($information, $data);

            return response()->json([
                'success' => true,
                'message' => 'Your information updated successfully',
                'data' => new UserInformationResource($information),
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update your information: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function destroy(Request $request, string $id)
    {
        try {
            $information = $this->service->findUserInformationOrFail($id);
            $this->authorize('delete', $information);

            $force = $request->get('force', false);
            $this->service->deleteUserInformation($information, $force);

            return response()->json([
                'success' => true,
                'message' => $force ? 'User information permanently deleted' : 'User information deleted successfully',
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete user information: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function deleteMyInformation(Request $request)
    {
        try {
            $user = auth()->user();
            $force = $request->get('force', false);
            $deleted = $this->service->deleteByUserId($user->user_id, $force);

            if (!$deleted) {
                return response()->json([
                    'success' => false,
                    'message' => 'User information not found',
                ], 404);
            }

            return response()->json([
                'success' => true,
                'message' => $force ? 'Your information permanently deleted' : 'Your information deleted successfully',
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete your information: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function restore(string $id)
    {
        try {
            $this->authorize('restore', UserInformation::class);
            
            $information = $this->service->restoreUserInformation($id);

            return response()->json([
                'success' => true,
                'message' => 'User information restored successfully',
                'data' => new UserInformationResource($information),
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to restore user information: ' . $e->getMessage(),
            ], 500);
        }
    }

    // =============================================
    // ADMIN ROUTES
    // =============================================

    public function statistics()
    {
        $this->authorize('viewStatistics', UserInformation::class);

        try {
            $stats = $this->service->getStatistics();

            return response()->json([
                'success' => true,
                'data' => $stats,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get statistics: ' . $e->getMessage(),
            ], 500);
        }
    }
}