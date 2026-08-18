<?php

namespace App\Swagger\Auth;

/**
 * @OA\Post(
 *      path="/api/v1/auth/login",
 *      operationId="authLogin",
 *      tags={"Authentication"},
 *      summary="User Login",
 *      description="Authenticate user and generate API token",
 *      @OA\RequestBody(
 *          required=true,
 *          @OA\MediaType(
 *              mediaType="application/json",
 *              @OA\Schema(
 *                  @OA\Property(property="email", type="string", format="email", example="user@example.com"),
 *                  @OA\Property(property="password", type="string", format="password", example="password123"),
 *                  required={"email", "password"}
 *              )
 *          )
 *      ),
 *      @OA\Response(
 *          response=200,
 *          description="Successful login",
 *          @OA\JsonContent(
 *              @OA\Property(property="success", type="boolean", example=true),
 *              @OA\Property(property="message", type="string", example="Login successful"),
 *              @OA\Property(property="data", type="object",
 *                  @OA\Property(property="user", type="object",
 *                      @OA\Property(property="id", type="integer", example=1),
 *                      @OA\Property(property="name", type="string", example="John Doe"),
 *                      @OA\Property(property="email", type="string", example="user@example.com"),
 *                      @OA\Property(property="tenant", type="object",
 *                          @OA\Property(property="id", type="integer", example=1),
 *                          @OA\Property(property="name", type="string", example="Company Inc"),
 *                          @OA\Property(property="slug", type="string", example="company-inc")
 *                      ),
 *                      @OA\Property(property="roles", type="array", @OA\Items(type="string")),
 *                      @OA\Property(property="permissions", type="array", @OA\Items(type="string"))
 *                  ),
 *                  @OA\Property(property="token", type="string", example="1234567890abcdef")
 *              )
 *          )
 *      ),
 *      @OA\Response(
 *          response=401,
 *          description="Invalid credentials",
 *          @OA\JsonContent(ref="#/components/schemas/UnauthorizedError")
 *      ),
 *      @OA\Response(
 *          response=422,
 *          description="Validation error",
 *          @OA\JsonContent(ref="#/components/schemas/ValidationError")
 *      )
 * )
 */

/**
 * @OA\Post(
 *      path="/api/v1/auth/logout",
 *      operationId="authLogout",
 *      tags={"Authentication"},
 *      summary="User Logout",
 *      description="Logout current user and revoke token",
 *      security={{"sanctum": {}}},
 *      @OA\Response(
 *          response=200,
 *          description="Successful logout",
 *          @OA\JsonContent(
 *              @OA\Property(property="success", type="boolean", example=true),
 *              @OA\Property(property="message", type="string", example="Logged out successfully")
 *          )
 *      ),
 *      @OA\Response(
 *          response=401,
 *          description="Unauthorized",
 *          @OA\JsonContent(ref="#/components/schemas/UnauthorizedError")
 *      )
 * )
 */

/**
 * @OA\Get(
 *      path="/api/v1/auth/user",
 *      operationId="authUser",
 *      tags={"Authentication"},
 *      summary="Get Current User",
 *      description="Get authenticated user details",
 *      security={{"sanctum": {}}},
 *      @OA\Response(
 *          response=200,
 *          description="User data retrieved successfully",
 *          @OA\JsonContent(
 *              @OA\Property(property="success", type="boolean", example=true),
 *              @OA\Property(property="data", type="object",
 *                  @OA\Property(property="id", type="integer", example=1),
 *                  @OA\Property(property="name", type="string", example="John Doe"),
 *                  @OA\Property(property="email", type="string", example="user@example.com"),
 *                  @OA\Property(property="tenant", type="object",
 *                      @OA\Property(property="id", type="integer", example=1),
 *                      @OA\Property(property="name", type="string", example="Company Inc"),
 *                      @OA\Property(property="settings", type="object")
 *                  ),
 *                  @OA\Property(property="roles", type="array", @OA\Items(type="string")),
 *                  @OA\Property(property="permissions", type="array", @OA\Items(type="string"))
 *              )
 *          )
 *      ),
 *      @OA\Response(
 *          response=401,
 *          description="Unauthorized",
 *          @OA\JsonContent(ref="#/components/schemas/UnauthorizedError")
 *      )
 * )
 */

/**
 * @OA\Put(
 *      path="/api/v1/auth/profile",
 *      operationId="updateProfile",
 *      tags={"Authentication"},
 *      summary="Update User Profile",
 *      description="Update authenticated user profile information",
 *      security={{"sanctum": {}}},
 *      @OA\RequestBody(
 *          required=true,
 *          @OA\MediaType(
 *              mediaType="multipart/form-data",
 *              @OA\Schema(
 *                  @OA\Property(property="name", type="string", example="John Smith"),
 *                  @OA\Property(property="phone", type="string", example="+1234567890"),
 *                  @OA\Property(property="avatar", type="string", format="binary")
 *              )
 *          )
 *      ),
 *      @OA\Response(
 *          response=200,
 *          description="Profile updated successfully",
 *          @OA\JsonContent(
 *              @OA\Property(property="success", type="boolean", example=true),
 *              @OA\Property(property="message", type="string", example="Profile updated successfully"),
 *              @OA\Property(property="data", type="object",
 *                  @OA\Property(property="name", type="string", example="John Smith"),
 *                  @OA\Property(property="phone", type="string", example="+1234567890")
 *              )
 *          )
 *      ),
 *      @OA\Response(
 *          response=401,
 *          description="Unauthorized",
 *          @OA\JsonContent(ref="#/components/schemas/UnauthorizedError")
 *      ),
 *      @OA\Response(
 *          response=422,
 *          description="Validation error",
 *          @OA\JsonContent(ref="#/components/schemas/ValidationError")
 *      )
 * )
 */

/**
 * @OA\Put(
 *      path="/api/v1/auth/password",
 *      operationId="changePassword",
 *      tags={"Authentication"},
 *      summary="Change Password",
 *      description="Change authenticated user password",
 *      security={{"sanctum": {}}},
 *      @OA\RequestBody(
 *          required=true,
 *          @OA\MediaType(
 *              mediaType="application/json",
 *              @OA\Schema(
 *                  @OA\Property(property="current_password", type="string", format="password", example="oldpassword123"),
 *                  @OA\Property(property="password", type="string", format="password", example="newpassword123"),
 *                  @OA\Property(property="password_confirmation", type="string", format="password", example="newpassword123"),
 *                  required={"current_password", "password", "password_confirmation"}
 *              )
 *          )
 *      ),
 *      @OA\Response(
 *          response=200,
 *          description="Password changed successfully",
 *          @OA\JsonContent(
 *              @OA\Property(property="success", type="boolean", example=true),
 *              @OA\Property(property="message", type="string", example="Password changed successfully")
 *          )
 *      ),
 *      @OA\Response(
 *          response=401,
 *          description="Unauthorized",
 *          @OA\JsonContent(ref="#/components/schemas/UnauthorizedError")
 *      ),
 *      @OA\Response(
 *          response=422,
 *          description="Validation error",
 *          @OA\JsonContent(ref="#/components/schemas/ValidationError")
 *      )
 * )
 */