<?php

namespace App\Swagger\Installments;

/**
 * @OA\Get(
 *      path="/api/v1/installments/overdue",
 *      operationId="getOverdueInstallments",
 *      tags={"Installments"},
 *      summary="Get Overdue Installments",
 *      description="Retrieve list of overdue installments",
 *      security={{"sanctum": {}}},
 *      @OA\Response(
 *          response=200,
 *          description="Successful response",
 *          @OA\JsonContent(
 *              @OA\Property(property="success", type="boolean", example=true),
 *              @OA\Property(property="data", type="array",
 *                  @OA\Items(
 *                      @OA\Property(property="id", type="integer", example=1),
 *                      @OA\Property(property="installment_number", type="integer", example=1),
 *                      @OA\Property(property="due_date", type="string", format="date", example="2024-02-01"),
 *                      @OA\Property(property="total_amount", type="number", format="float", example=100.00),
 *                      @OA\Property(property="paid_amount", type="number", format="float", example=50.00),
 *                      @OA\Property(property="creditSale", type="object",
 *                          @OA\Property(property="credit_sale_number", type="string", example="CS-2024-0001"),
 *                          @OA\Property(property="customer", type="object",
 *                              @OA\Property(property="customer", type="object",
 *                                  @OA\Property(property="name", type="string", example="John Customer")
 *                              )
 *                          )
 *                      )
 *                  )
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
 * @OA\Get(
 *      path="/api/v1/installments/due-today",
 *      operationId="getDueTodayInstallments",
 *      tags={"Installments"},
 *      summary="Get Today's Due Installments",
 *      description="Retrieve list of installments due today",
 *      security={{"sanctum": {}}},
 *      @OA\Response(
 *          response=200,
 *          description="Successful response",
 *          @OA\JsonContent(
 *              @OA\Property(property="success", type="boolean", example=true),
 *              @OA\Property(property="data", type="array", @OA\Items(type="object"))
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
 *      path="/api/v1/installments/upcoming",
 *      operationId="getUpcomingInstallments",
 *      tags={"Installments"},
 *      summary="Get Upcoming Installments",
 *      description="Retrieve list of upcoming installments (next 7 days)",
 *      security={{"sanctum": {}}},
 *      @OA\Response(
 *          response=200,
 *          description="Successful response",
 *          @OA\JsonContent(
 *              @OA\Property(property="success", type="boolean", example=true),
 *              @OA\Property(property="data", type="array", @OA\Items(type="object"))
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
 *      path="/api/v1/installments",
 *      operationId="getInstallmentsList",
 *      tags={"Installments"},
 *      summary="Get Installments List",
 *      description="Retrieve paginated list of installments with optional filters",
 *      security={{"sanctum": {}}},
 *      @OA\Parameter(
 *          name="search",
 *          in="query",
 *          description="Search by customer name or credit sale number",
 *          required=false,
 *          @OA\Schema(type="string")
 *      ),
 *      @OA\Parameter(
 *          name="status",
 *          in="query",
 *          description="Filter by status (pending, paid, partial, overdue)",
 *          required=false,
 *          @OA\Schema(type="string")
 *      ),
 *      @OA\Parameter(
 *          name="customer_id",
 *          in="query",
 *          description="Filter by customer ID",
 *          required=false,
 *          @OA\Schema(type="integer")
 *      ),
 *      @OA\Parameter(
 *          name="per_page",
 *          in="query",
 *          description="Number of items per page",
 *          required=false,
 *          @OA\Schema(type="integer", default=15)
 *      ),
 *      @OA\Response(
 *          response=200,
 *          description="Successful response",
 *          @OA\JsonContent(
 *              @OA\Property(property="success", type="boolean", example=true),
 *              @OA\Property(property="data", type="object",
 *                  @OA\Property(property="data", type="array", @OA\Items(type="object")),
 *                  @OA\Property(property="links", type="object"),
 *                  @OA\Property(property="meta", type="object")
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
 * @OA\Get(
 *      path="/api/v1/installments/{id}",
 *      operationId="getInstallment",
 *      tags={"Installments"},
 *      summary="Get Installment Details",
 *      description="Retrieve detailed information about a specific installment",
 *      security={{"sanctum": {}}},
 *      @OA\Parameter(
 *          name="id",
 *          in="path",
 *          description="Installment ID",
 *          required=true,
 *          @OA\Schema(type="integer")
 *      ),
 *      @OA\Response(
 *          response=200,
 *          description="Successful response",
 *          @OA\JsonContent(
 *              @OA\Property(property="success", type="boolean", example=true),
 *              @OA\Property(property="data", type="object",
 *                  @OA\Property(property="id", type="integer", example=1),
 *                  @OA\Property(property="installment_number", type="integer", example=1),
 *                  @OA\Property(property="due_date", type="string", format="date", example="2024-03-01"),
 *                  @OA\Property(property="total_amount", type="number", format="float", example=100.00),
 *                  @OA\Property(property="paid_amount", type="number", format="float", example=50.00),
 *                  @OA\Property(property="status", type="string", example="partial"),
 *                  @OA\Property(property="creditSale", type="object",
 *                      @OA\Property(property="credit_sale_number", type="string", example="CS-2024-0001"),
 *                      @OA\Property(property="customer", type="object")
 *                  )
 *              )
 *          )
 *      ),
 *      @OA\Response(
 *          response=404,
 *          description="Installment not found",
 *          @OA\JsonContent(ref="#/components/schemas/NotFoundError")
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
 *      path="/api/v1/installments/{id}",
 *      operationId="updateInstallment",
 *      tags={"Installments"},
 *      summary="Update Installment",
 *      description="Update an existing installment",
 *      security={{"sanctum": {}}},
 *      @OA\Parameter(
 *          name="id",
 *          in="path",
 *          description="Installment ID",
 *          required=true,
 *          @OA\Schema(type="integer")
 *      ),
 *      @OA\RequestBody(
 *          required=true,
 *          @OA\MediaType(
 *              mediaType="application/json",
 *              @OA\Schema(
 *                  @OA\Property(property="due_date", type="string", format="date", example="2024-04-01"),
 *                  @OA\Property(property="status", type="string", example="pending"),
 *                  @OA\Property(property="notes", type="string", example="Extended payment term")
 *              )
 *          )
 *      ),
 *      @OA\Response(
 *          response=200,
 *          description="Installment updated successfully",
 *          @OA\JsonContent(
 *              @OA\Property(property="success", type="boolean", example=true),
 *              @OA\Property(property="message", type="string", example="Installment updated successfully."),
 *              @OA\Property(property="data", type="object")
 *          )
 *      ),
 *      @OA\Response(
 *          response=404,
 *          description="Installment not found",
 *          @OA\JsonContent(ref="#/components/schemas/NotFoundError")
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
 * @OA\Post(
 *      path="/api/v1/installments/{id}/pay",
 *      operationId="payInstallment",
 *      tags={"Installments"},
 *      summary="Pay Installment",
 *      description="Record payment for a specific installment",
 *      security={{"sanctum": {}}},
 *      @OA\Parameter(
 *          name="id",
 *          in="path",
 *          description="Installment ID",
 *          required=true,
 *          @OA\Schema(type="integer")
 *      ),
 *      @OA\RequestBody(
 *          required=true,
 *          @OA\MediaType(
 *              mediaType="application/json",
 *              @OA\Schema(
 *                  @OA\Property(property="payment_method_id", type="integer", example=1),
 *                  @OA\Property(property="amount", type="number", format="float", example=100.00),
 *                  @OA\Property(property="reference", type="string", example="PAY-001"),
 *                  @OA\Property(property="notes", type="string", example="Full payment"),
 *                  required={"payment_method_id", "amount"}
 *              )
 *          )
 *      ),
 *      @OA\Response(
 *          response=200,
 *          description="Payment recorded successfully",
 *          @OA\JsonContent(
 *              @OA\Property(property="success", type="boolean", example=true),
 *              @OA\Property(property="message", type="string", example="Payment recorded successfully."),
 *              @OA\Property(property="data", type="object")
 *          )
 *      ),
 *      @OA\Response(
 *          response=404,
 *          description="Installment not found",
 *          @OA\JsonContent(ref="#/components/schemas/NotFoundError")
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