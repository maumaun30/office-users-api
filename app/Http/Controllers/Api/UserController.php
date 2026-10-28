<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class UserController extends Controller
{
    /**
     * Paginated user directory (admins and service accounts).
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('view-users');

        $perPage = min(max((int) $request->query('per_page', 50), 1), 100);

        return UserResource::collection(User::orderBy('id')->paginate($perPage));
    }

    /**
     * A single user (admins, service accounts, or the user themself).
     */
    public function show(User $user): UserResource
    {
        Gate::authorize('view-user', $user);

        return new UserResource($user);
    }
}
