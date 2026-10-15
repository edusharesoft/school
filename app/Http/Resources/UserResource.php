<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\User */
class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'name_bn' => $this->name_bn,
            'email' => $this->email,
            'phone' => $this->phone,
            'user_type' => $this->user_type?->value,
            'portal' => $this->portal(),
            'locale' => $this->locale,
            'roles' => $this->whenLoaded('roles', fn () => $this->roles->pluck('name')),
            'permissions' => $this->when($request->routeIs('api.v1.auth.me'), fn () => $this->getAllPermissions()->pluck('name')),
        ];
    }
}
