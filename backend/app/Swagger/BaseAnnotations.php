<?php

/**
 * @OA\Info(
 *      version="1.0.0",
 *      title="ERP System API Documentation",
 *      description="Complete API documentation for the Enterprise Resource Planning System",
 *      @OA\Contact(
 *          email="support@erp-system.com"
 *      ),
 *      @OA\License(
 *          name="MIT",
 *          url="https://opensource.org/licenses/MIT"
 *      )
 * )
 */

/**
 * @OA\Server(
 *      url=L5_SWAGGER_CONST_HOST,
 *      description="ERP API Server"
 * )
 */

/**
 * @OA\SecurityScheme(
 *      securityScheme="sanctum",
 *      type="apiKey",
 *      name="Authorization",
 *      in="header",
 *      description="Enter token in format (Bearer <token>)"
 * )
 */

/**
 * @OA\Schema(
 *      schema="ApiResponse",
 *      @OA\Property(property="success", type="boolean", example=true),
 *      @OA\Property(property="message", type="string", example="Operation successful"),
 *      @OA\Property(property="data", type="object")
 * )
 */

/**
 * @OA\Schema(
 *      schema="ValidationError",
 *      @OA\Property(property="success", type="boolean", example=false),
 *      @OA\Property(property="message", type="string", example="Validation failed"),
 *      @OA\Property(property="errors", type="object")
 * )
 */

/**
 * @OA\Schema(
 *      schema="UnauthorizedError",
 *      @OA\Property(property="success", type="boolean", example=false),
 *      @OA\Property(property="message", type="string", example="Unauthorized"),
 *      @OA\Property(property="error_code", type="string", example="UNAUTHORIZED")
 * )
 */

/**
 * @OA\Schema(
 *      schema="NotFoundError",
 *      @OA\Property(property="success", type="boolean", example=false),
 *      @OA\Property(property="message", type="string", example="Resource not found"),
 *      @OA\Property(property="error_code", type="string", example="NOT_FOUND")
 * )
 */

/**
 * @OA\Schema(
 *      schema="ServerError",
 *      @OA\Property(property="success", type="boolean", example=false),
 *      @OA\Property(property="message", type="string", example="Internal server error"),
 *      @OA\Property(property="error_code", type="string", example="SERVER_ERROR")
 * )
 */