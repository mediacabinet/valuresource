<?php
namespace ValuResource\Service;

use ValuResource\Service\Exception\UnsupportedNamespaceException;

use ValuSo\Exception\UnsupportedOperationException;
use ValuSo\Command\CommandInterface;
use ValuSo\Feature;
use ValuSo\Feature\ServiceBrokerTrait;
use ValuSo\Feature\OptionsTrait;

class ProxyService
    implements ResourceServiceInterface,
               Feature\ServiceBrokerAwareInterface
{
    use ServiceBrokerTrait;
    
    /**
     * Name of the service, where the operations are
     * proxied to by default
     * 
     * @var string
     */
    protected $defaultService;
    
    /**
     * Resource spec map
     * 
     * @var array
     */
    protected $specMap = array();
    
    /**
     * Command
     * 
     * @var CommandInterface
     */
    protected $command;
    
    /**
     * Array of supported namespaces
     */
    protected $namespaces = array();
    
    public function __invoke(CommandInterface $command)
    {
        $this->command = $command;
        
        $ns = $command->getParam('ns', 0);
        $this->assertNamespace($ns);
        
        switch ($command->getOperation()) {
            case 'getNamespaces':
                return $this->namespaces;
                break;
            case 'exists':
            case 'remove':
                return call_user_func_array(
                    [$this, $command->getOperation()], 
                    $this->resolveArgs($command, ['ns', 'resourceId']));
                break;
            case 'findBy':
            case 'findManyBy':
                $args = $this->resolveArgs($command, ['ns', 'property', 'value', 'specs']);
                
                $response = call_user_func_array(
                    [$this, $command->getOperation()],
                    $args);
                
                if ($command->getOperation() === 'findMany') {
                    foreach ($response as $key => &$value) {
                        $this->mapResponse($value, $args['specs']);
                    }
                } else {
                    $this->mapResponse($response, $args['specs']);
                }
                
                return $response;
                break;
            case 'create':
                return call_user_func_array(
                    [$this, $command->getOperation()],
                    $this->resolveArgs($command, ['ns', 'specs']));
                    break;
            case 'update':
                return call_user_func_array(
                    [$this, $command->getOperation()],
                    $this->resolveArgs($command, ['ns', 'resourceId', 'specs']));
                break;
        }
    }
    
    /**
     * @see \ValuResource\Service\ResourceServiceInterface::create()
     */
    public function create($ns, $specs)
    {
        throw $this->newUnsupportedOperationException($ns);
    }

	/**
     * @see \ValuResource\Service\ResourceServiceInterface::exists()
     */
    public function exists($ns, $resourceId)
    {
        throw $this->newUnsupportedOperationException($ns);
    }

	/**
     * @see \ValuResource\Service\ResourceServiceInterface::findBy()
     */
    public function findBy($ns, $property, $value, $specs = null)
    {
        throw $this->newUnsupportedOperationException($ns);
    }

	/**
     * @see \ValuResource\Service\ResourceServiceInterface::findManyBy()
     */
    public function findManyBy($ns, $property, $value, $specs = null)
    {
        throw $this->newUnsupportedOperationException($ns);
    }

	/**
     * @see \ValuResource\Service\ResourceServiceInterface::remove()
     */
    public function remove($ns, $resourceId)
    {
        throw $this->newUnsupportedOperationException($ns);
    }

	/**
     * @see \ValuResource\Service\ResourceServiceInterface::update()
     */
    public function update($ns, $resourceId, $specs)
    {
        throw $this->newUnsupportedOperationException($ns);
    }

    /**
     * Resolve argument list from command
     * 
     * @return array
     */
	protected function resolveArgs(CommandInterface $command, $args)
    {
        $arguments = [];
        foreach ($args as $key => $arg) {
            if ($command->hasParam($arg)) {
                $arguments[$arg] = $command->getParam($arg);
            } elseif ($command->hasParam($key)) {
                $arguments[$arg] = $command->getParam($key);
            }
            
            if ($arg === 'property') {
                // Map property name
                $arguments[$arg] = $this->mapProperty($arguments[$arg]);
            } elseif ($arg === 'specs') {
                // Map specs array
                $arguments[$arg] = $this->mapSpecs($arguments[$arg]);
            }
        }
        
        return $arguments;
    }
    
    /**
     * Proxy command
     * 
     * @param array $params
     * @param string $operation
     * @param string $service
     */
    protected function proxy($params = null, $operation = null, $service = null)
    {
        if ($service === null) {
            
            if (!$this->defaultService) {
                throw new \RuntimeException('Proxy service is not defined');
            }
            
            $service = $this->defaultService;
        }
        
        $this->command->setService($service);
        
        if ($operation !== null) {
            $this->command->setOperation($operation);
        }
        
        if ($params !== null) {
            $this->command->setParams($params);
        }
        
        return $this->getServiceBroker()->dispatch($this->command)->first();
    }
    
    /**
     * Map spec array
     * 
     * @param array $specs
     */
    protected function mapSpecs($specs)
    {
        if ($specs === null) {
            return null;
        }
        
        if (is_array($specs)) {
            foreach ($specs as $key => $value) {
                $mapped = $this->mapProperty($key);
                
                if ($mapped !== false) {
                    $specs[$mapped] = $value;
                }
                
                if ($mapped !== $key) {
                    unset($specs[$key]);
                }
            }
            
            return $specs;
        } elseif (is_string($specs)) {
            return $this->mapProperty($specs);
        }
    }
    
    /**
     * Map keys in response array according to target
     * specs
     * 
     * @return array
     */
    protected function mapResponse(&$response, $targetSpecs)
    {
        if (!is_array($response)) {
            return;
        } elseif (!is_array($targetSpecs)) {
            return;
        } else {
            foreach ($targetSpecs as $key => $value) {
                
                if (!array_key_exists($key, $response)) {
                    continue;
                }
                
                $resourceSpec = array_search($key, $this->specMap);
                
                if ($resourceSpec !== false) {
                    $response[$resourceSpec] = $response[$key];
                    
                    if ($resourceSpec !== $key) {
                        unset($response[$key]);
                    }
                }
            }
        }
    }
    
    /**
     * Map spec
     * 
     * @return string|boolean New spec name, or false if spec is not supported
     */
    protected function mapProperty($property)
    {
        if (array_key_exists($property, $this->specMap)) {
            return $this->specMap[$property];
        } else {
            return $property;
        }
    }
    
    /**
     * Assert that namespace is supported
     * 
     * @throws UnsupportedNamespaceException
     */
    protected function assertNamespace($ns)
    {
        if ($ns === '*') {
            return;
        }
        
        if (!in_array($ns, $this->namespaces)) {
            throw new UnsupportedNamespaceException(
                'Namespace %NS% is not supported', ['NS' => $ns]);
        }
    }
    
    /**
     * Create new exception
     */
    private function newUnsupportedOperationException($ns)
    {
        return new UnsupportedOperationException(
            "Resource service for namespace %NS% doesn't support operation %OPERATION%",
            array('NS' => $ns, 'OPERATION' => $this->command->getOperation()));
    }
}