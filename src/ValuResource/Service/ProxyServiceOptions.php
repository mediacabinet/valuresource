<?php
/**
 * Valu Resource Module
 *
 * @copyright Copyright (c) 2012-2013 Media Cabinet (www.mediacabinet.fi)
 * @license   BSD 2 License
 */
namespace ValuFileSystem\Service\Setup;

use Zend\Stdlib\AbstractOptions;

class ProxyServiceOptions extends AbstractOptions{

    /**
     * Operation map for mapping operations to correct services
     * 
     * Example of valid operation map:
     * <code>
     * $operationMap = array(
     *     'update'        => 'User.update',
     *     'user::create'  => 'User.create'
     *     'group::create' => 'Group.create'
     * );
     * </code>
     * 
     * @var array
     */
    protected $operationMap = array();
    
    /**
     * Spec map for mapping resource specs to target specs
     * 
     * Example of valid spec map:
     * <code>
     * $specMap = array(
     *     'resourceId'    => 'id',
     *     'resouceName'   => 'name'
     *     'group::resourceName' => 'groupName'
     * );
     * </code>
     * 
     * @var array
     */
    protected $specMap = array();
    
    /**
     * Array of supported namespaces
     * 
     * @var array
     */
    protected $namespaces = array();
    
	/**
     * @return array
     */
    public function getOperationMap()
    {
        return $this->operationMap;
    }

	/**
     * @param array $operationMap
     */
    public function setOperationMap(array $operationMap)
    {
        $this->operationMap = $operationMap;
    }
    
    /**
     * @return array
     */
    public function getSpecMap()
    {
        return $this->specSpecMap;
    }

	/**
     * @param array $specSpecMap
     */
    public function setSpecMap(array $specSpecMap)
    {
        $this->specSpecMap = $specSpecMap;
    }
    
	/**
     * @return array
     */
    public function getNamespaces()
    {
        return $this->namespaces;
    }

	/**
     * @param array
     */
    public function setNamespaces(array $namespaces)
    {
        $this->namespaces = $namespaces;
    }


}