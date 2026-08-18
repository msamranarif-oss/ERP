<?php

namespace App\Swagger\CreditSales;

/**
 * @OA\Get(
 *      path="/api/v1/credit-sales",
 *      operationId="getCreditSalesList",
 *      tags={"Credit Sales"},
 *      summary="Get Credit Sales List",
 *      description="Retrieve paginated list of credit sales with optional filters",
 *      security={{"sanctum": {}}},
 *      @OA\Parameter(
 *          name="search",
 *          in="query",
 *          description="Search by credit sale number or customer name",
 *          required=false,
 *          @OA\Schema(type="string")
 *      ),
 *      @OA\Parameter(
 *          name="status",
 *          in="query",
 *          description="Filter by status (active, completed, cancelled, defaulted)",
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
 *                  @OA\Property(property="data", type="array",
 *                      @OA\Items(
 *                          @OA\Property(property="id", type="integer", example=1),
 *                          @OA\Property(property="credit_sale_number", type="string", example="CS-2024-0001"),
 *                          @OA\Property(property="customer", type="object",
 *                              @OA\Property(property="customer", type="object",
 *                                  @OA\Property(property="name", type="string", example="John Customer")
 *                              )
 *                          ),
 *                          @OA\Property(property="total_amount", type="number", format="float", example=1000.00),
 *                          @OA\Property(property="status", type="string", example="active"),
 *                          @OA\Property(property="created_at", type="string", format="date-time")
 *                      )
 *                  ),
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
 * @OA\Post(
 *      path="/api/v1/credit-sales",
 *      operationId="createCreditSale",
 *      tags={"Credit Sales"},
 *      summary="Create Credit Sale",
 *      description="Create a new credit sale with items and installments",
 *      security={{"sanctum": {}}},
 *      @OA\RequestBody(
 *          required=true,
 *          @OA\MediaType(
 *              mediaType="application/json",
 *              @OA\Schema(
 *                  @OA\Property(property="customer_id", type="integer", example=1),
 *                  @OA\Property(property="down_payment", type="number", format="float", example=200.00),
 *                  @OA\Property(property="loan_amount", type="number", format="float", example=800.00),
 *                  @OA\Property(property="interest_rate", type="number", format="float", example=5.0),
 *                  @OA\Property(property="number_of_installments", type="integer", example=12),
 *                  @OA\Property(property="installment_frequency", type="string", example="monthly"),
 *                  @OA\Property(property="first_installment_date", type="string", format="date", example="2024-03-01"),
 *                  @OA\Property(property="total_amount", type="number", format="float", example=1000.00),
 *                  @OA\Property(property="discount_amount", type="number", format="float", example=0.00),
 *                  @OA\Property(property="tax_amount", type="number", format="float", example=0.00),
 *                  @OA\Property(property="shipping_cost", type="number", format="float", example=0.00),
 *                  @OA\Property(property="notes", type="string", example="Special terms apply"),
 *                  @OA\Property(property="items", type="array",
 *                      @OA\Items(
 *                          @OA\Property(property="product_id", type="integer", example=1),
 *                          @OA\Property(property="quantity", type="number", format="float", example=2),
 *                          @OA\Property(property="unit_price", type="number", format="float", example=500.00),
 *                          @OA\Property(property="discount_percent", type="number", format="float", example=0.0),
 *                          @OA\Property(property="tax_percent", type="number", format="float", example=0.0)
 *                      )
 *                  ),
 *                  required={"customer_id", "down_payment", "loan_amount", "interest_rate", "number_of_installments", "installment_frequency", "first_installment_date", "total_amount", "items"}
 *              )
 *          )
 *      ),
 *      @OA\Response(
 *          response=201,
 *          description="Credit sale created successfully",
 *          @OA\JsonContent(
 *              @OA\Property(property="success", type="boolean", example=true),
 *              @OA\Property(property="message", type="string", example="Credit sale created successfully."),
 *              @OA\Property(property="data", type="object",
 *                  @OA\Property(property="id", type="integer", example=1),
 *                  @OA\Property(property="credit_sale_number", type="string", example="CS-2024-0001")
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
 * @OA\Get(
 *      path="/api/v1/credit-sales/{id}",
 *      operationId="getCreditSale",
 *      tags={"Credit Sales"},
 *      summary="Get Credit Sale Details",
 *      description="Retrieve detailed information about a specific credit sale",
 *      security={{"sanctum": {}}},
 *      @OA\Parameter(
 *          name="id",
 *          in="path",
 *          description="Credit Sale ID",
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
 *                  @OA\Property(property="credit_sale_number", type="string", example="CS-2024-0001"),
 *                  @OA\Property(property="customer", type="object"),
 *                  @OA\Property(property="items", type="array", @OA\Items(type="object")),
 *                  @OA\Property(property="installments", type="array", @OA\Items(type="object")),
 *                  @OA\Property(property="total_amount", type="number", format="float", example=1000.00),
 *                  @OA\Property(property="status", type="string", example="active")
 *              )
 *          )
 *      ),
 *      @OA\Response(
 *          response=404,
 *          description="Credit sale not found",
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
 *      path="/api/v1/credit-sales/{id}",
 *      operationId="updateCreditSale",
 *      tags={"Credit Sales"},
 *      summary="Update Credit Sale",
 *      description="Update an existing credit sale",
 *      security={{"sanctum": {}}},
 *      @OA\Parameter(
 *          name="id",
 *          in="path",
 *          description="Credit Sale ID",
 *          required=true,
 *          @OA\Schema(type="integer")
 *      ),
 *      @OA\RequestBody(
 *          required=true,
 *          @OA\MediaType(
 *              mediaType="application/json",
 *              @OA\Schema(
 *                  @OA\Property(property="down_payment", type="number", format="float", example=300.00),
 *                  @OA\Property(property="loan_amount", type="number", format="float", example=700.00),
 *                  @OA\Property(property="interest_rate", type="number", format="float", example=6.0),
 *                  @OA\Property(property="status", type="string", example="active")
 *              )
 *          )
 *      ),
 *      @OA\Response(
 *          response=200,
 *          description="Credit sale updated successfully",
 *          @OA\JsonContent(
 *              @OA\Property(property="success", type="boolean", example=true),
 *              @OA\Property(property="message", type="string", example="Credit sale updated successfully."),
 *              @OA\Property(property="data", type="object")
 *          )
 *      ),
 *      @OA\Response(
 *          response=404,
 *          description="Credit sale not found",
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
 * @OA\Delete(
 *      path="/api/v1/credit-sales/{id}",
 *      operationId="deleteCreditSale",
 *      tags={"Credit Sales"},
 *      summary="Delete Credit Sale",
 *      description="Delete a credit sale (only if no pending installments)",
 *      security={{"sanctum": {}}},
 *      @OA\Parameter(
 *          name="id",
 *          in="path",
 *          description="Credit Sale ID",
 *          required=true,
 *          @OA\Schema(type="integer")
 *      ),
 *      @OA\Response(
 *          response=200,
 *          description="Credit sale deleted successfully",
 *          @OA\JsonContent(
 *              @OA\Property(property="success", type="boolean", example=true),
 *              @OA\Property(property="message", type="string", example="Credit sale deleted successfully.")
 *          )
 *      ),
 *      @OA\Response(
 *          response=404,
 *          description="Credit sale not found",
 *          @OA\JsonContent(ref="#/components/schemas/NotFoundError")
 *      ),
 *      @OA\Response(
 *          response=401,
 *          description="Unauthorized",
 *          @OA\JsonContent(ref="#/components/schemas/UnauthorizedError")
 *      ),
 *      @OA\Response(
 *          response=422,
 *          description="Cannot delete credit sale with pending installments",
 *          @OA\JsonContent(
 *              @OA\Property(property="success", type="boolean", example=false),
 *              @OA\Property(property="message", type="string", example="Cannot delete credit sale with pending installments.")
 *          )
 *      )
 * )
 */

/**
 * @OA\Post(
 *      path="/api/v1/credit-sales/{id}/payment",
 *      operationId="recordCreditSalePayment",
 *      tags={"Credit Sales"},
 *      summary="Record Payment",
 *      description="Record a payment for a credit sale installment",
 *      security={{"sanctum": {}}},
 *      @OA\Parameter(
 *          name="id",
 *          in="path",
 *          description="Credit Sale ID",
 *          required=true,
 *          @OA\Schema(type="integer")
 *      ),
 *      @OA\RequestBody(
 *          required=true,
 *          @OA\MediaType(
 *              mediaType="application/json",
 *              @OA\Schema(
 *                  @OA\Property(property="installment_id", type="integer", example=1),
 *                  @OA\Property(property="payment_method_id", type="integer", example=1),
 *                  @OA\Property(property="amount", type="number", format="float", example=100.00),
 *                  @OA\Property(property="reference", type="string", example="PAY-001"),
 *                  @OA\Property(property="notes", type="string", example="Monthly payment"),
 *                  required={"installment_id", "payment_method_id", "amount"}
 *              )
 *          )
 *      ),
 *      @OA\Response(
 *          response=201,
 *          description="Payment recorded successfully",
 *          @OA\JsonContent(
 *              @OA\Property(property="success", type="boolean", example=true),
 *              @OA\Property(property="message", type="string", example="Payment recorded successfully."),
 *              @OA\Property(property="data", type="object",
 *                  @OA\Property(property="id", type="integer", example=1),
 *                  @OA\Property(property="payment_number", type="string", example="PMT-2024-0001")
 *              )
 *          )
 *      ),
 *      @OA\Response(
 *          response=404,
 *          description="Credit sale or installment not found",
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
 * @OA\Get(
 *      path="/api/v1/credit-sales/{id}/schedule",
 *      operationId="getCreditSaleSchedule",
 *      tags={"Credit Sales"},
 *      summary="Get Payment Schedule",
 *      description="Retrieve installment schedule for a credit sale",
 *      security={{"sanctum": {}}},
 *      @OA\Parameter(
 *          name="id",
 *          in="path",
 *          description="Credit Sale ID",
 *          required=true,
 *          @OA\Schema(type="integer")
 *      ),
 *      @OA\Response(
 *          response=200,
 *          description="Schedule retrieved successfully",
 *          @OA\JsonContent(
 *              @OA\Property(property="success", type="boolean", example=true),
 *              @OA\Property(property="data", type="array",
 *                  @OA\Items(
 *                      @OA\Property(property="id", type="integer", example=1),
 *                      @OA\Property(property="installment_number", type="integer", example=1),
 *                      @OA\Property(property="due_date", type="string", format="date", example="2024-03-01"),
 *                      @OA\Property(property="total_amount", type="number", format="float", example=100.00),
 *                      @OA\Property(property="paid_amount", type="number", format="float", example=50.00),
 *                      @OA\Property(property="status", type="string", example="partial")
 *                  )
 *              )
 *          )
 *      ),
 *      @OA\Response(
 *          response=404,
 *          description="Credit sale not found",
 *          @OA\JsonContent(ref="#/components/schemas/NotFoundError")
 *      ),
 *      @OA\Response(
 *          response=401,
 *          description="Unauthorized",
 *          @OA\JsonContent(ref="#/components/schemas/UnauthorizedError")
 *      )
 * )
 */