<?php
/**
 * WooCommerce REST API Client Interface
 * 
 * Defines the contract for WooCommerce REST API communication.
 * Follows Interface Segregation Principle (ISP) - focused interface.
 * 
 * @since 1.0.0
 * @package EXPORT_WOO
 */

/**
 * Interface WooRestClientInterface
 * 
 * @since 1.0.0
 */
interface WooRestClientInterface
{
    /**
     * Send GET request to WooCommerce REST API
     * 
     * @since 1.0.0
     * @param string $endpoint API endpoint (e.g., 'products', 'orders/123')
     * @param array $params Query parameters
     * @return array Response data
     * @throws \RuntimeException If request fails
     */
    public function get(string $endpoint, array $params = []): array;
    
    /**
     * Send POST request to WooCommerce REST API
     * 
     * @since 1.0.0
     * @param string $endpoint API endpoint
     * @param array $data Data to send in request body
     * @return array Response data
     * @throws \RuntimeException If request fails
     */
    public function post(string $endpoint, array $data = []): array;
    
    /**
     * Send PUT request to WooCommerce REST API
     * 
     * @since 1.0.0
     * @param string $endpoint API endpoint
     * @param array $data Data to send in request body
     * @return array Response data
     * @throws \RuntimeException If request fails
     */
    public function put(string $endpoint, array $data = []): array;
    
    /**
     * Send DELETE request to WooCommerce REST API
     * 
     * @since 1.0.0
     * @param string $endpoint API endpoint
     * @param array $params Query parameters
     * @return array Response data
     * @throws \RuntimeException If request fails
     */
    public function delete(string $endpoint, array $params = []): array;
}
