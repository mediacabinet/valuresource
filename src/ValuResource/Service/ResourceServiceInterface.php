<?php
namespace ValuResource\Service;

/**
 * Resource service interface
 *
 */
interface ResourceServiceInterface
{
    
    /**
     * Test whether or not resource with given ID exists
     * 
     * @param string $ns
     * @param string $resourceId
     * 
     * @return boolean
     */
    public function exists($ns, $resourceId);
    
    /**
     * Find resource by named property
     * 
     * @param string $ns
     * @param string $property
     * @param mixed $value
     * @param array|null $specs
     */
    public function findBy($ns, $property, $value, $specs = null);
    
    /**
     * Find many resources by named property
     *
     * @param string $ns
     * @param string $property
     * @param mixed $value
     * @param array|null $specs
     */
    public function findManyBy($ns, $property, $value, $specs = null);
    
    /**
     * Create a new resource
     * 
     * @return mixed
     */
    public function create($ns, $specs);
    
    /**
     * Update resource
     * 
     * @return boolean
     */
    public function update($ns, $resourceId, $specs);
    
    /**
     * Remove resource by its ID
     * 
     * @param string $ns
     * @param string $resourceId
     */
    public function remove($ns, $resourceId);
}