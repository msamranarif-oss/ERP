<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Auth;

class CachingService
{
    /**
     * Cache key prefix for tenant-specific data
     */
    private static function getTenantPrefix(): string
    {
        $tenantId = Auth::check() ? Auth::user()->tenant_id : 'global';
        return "tenant_{$tenantId}";
    }

    /**
     * Generate cache key with tenant context
     */
    public static function generateKey(string $entity, string $identifier = '', array $tags = []): string
    {
        $baseKey = self::getTenantPrefix() . ":{$entity}";
        
        if ($identifier) {
            $baseKey .= ":{$identifier}";
        }
        
        if (!empty($tags)) {
            $baseKey .= ':' . implode(':', $tags);
        }
        
        return $baseKey;
    }

    /**
     * Cache data with automatic tenant scoping
     */
    public static function remember(string $key, int $minutes, callable $callback, array $tags = [])
    {
        $cacheKey = self::generateKey($key);
        return Cache::remember($cacheKey, $minutes * 60, $callback);
    }

    /**
     * Cache data forever with automatic tenant scoping
     */
    public static function rememberForever(string $key, callable $callback, array $tags = [])
    {
        $cacheKey = self::generateKey($key);
        return Cache::rememberForever($cacheKey, $callback);
    }

    /**
     * Get cached data
     */
    public static function get(string $key, $default = null)
    {
        $cacheKey = self::generateKey($key);
        return Cache::get($cacheKey, $default);
    }

    /**
     * Store data in cache
     */
    public static function put(string $key, $value, int $minutes = 60, array $tags = [])
    {
        $cacheKey = self::generateKey($key);
        Cache::put($cacheKey, $value, $minutes * 60);
    }

    /**
     * Forget cached data
     */
    public static function forget(string $key, array $tags = [])
    {
        $cacheKey = self::generateKey($key);
        Cache::forget($cacheKey);
    }

    /**
     * Clear all tenant-specific cache
     */
    public static function clearTenantCache()
    {
        $tenantPrefix = self::getTenantPrefix();
        // With the database driver, we clear the entire cache store
        Cache::flush();
        LoggingService::info("Cache cleared for tenant: {$tenantPrefix}");
    }

    /**
     * Cache common dashboard data
     */
    public static function cacheDashboardData(int $userId, callable $callback, int $minutes = 30)
    {
        $key = "dashboard:user_{$userId}:" . date('Y-m-d');
        return self::remember($key, $minutes, $callback);
    }

    /**
     * Cache product catalog data
     */
    public static function cacheProductCatalog(callable $callback, int $minutes = 60)
    {
        $key = 'product_catalog:active';
        return self::remember($key, $minutes, $callback);
    }

    /**
     * Cache customer data
     */
    public static function cacheCustomerData(int $customerId, callable $callback, int $minutes = 120)
    {
        $key = "customer:{$customerId}";
        return self::remember($key, $minutes, $callback);
    }

    /**
     * Cache credit sale data
     */
    public static function cacheCreditSaleData(int $creditSaleId, callable $callback, int $minutes = 60)
    {
        $key = "credit_sale:{$creditSaleId}";
        return self::remember($key, $minutes, $callback);
    }

    /**
     * Invalidate related caches when data changes
     */
    public static function invalidateRelatedCaches(string $entityType, $entityId = null)
    {
        $keysToForget = [];
        
        switch ($entityType) {
            case 'product':
                $keysToForget[] = 'product_catalog:active';
                if ($entityId) {
                    $keysToForget[] = "product:{$entityId}";
                }
                break;
                
            case 'customer':
                if ($entityId) {
                    $keysToForget[] = "customer:{$entityId}";
                }
                break;
                
            case 'credit_sale':
                if ($entityId) {
                    $keysToForget[] = "credit_sale:{$entityId}";
                    $keysToForget[] = "customer_credit_info:related_to_{$entityId}";
                }
                break;
                
            case 'payment':
                if ($entityId) {
                    $keysToForget[] = "credit_sale_payments:related_to_{$entityId}";
                }
                break;
        }
        
        foreach ($keysToForget as $key) {
            self::forget($key);
        }
        
        if (!empty($keysToForget)) {
            LoggingService::info("Invalidated caches for {$entityType}", ['keys' => $keysToForget]);
        }
    }
}