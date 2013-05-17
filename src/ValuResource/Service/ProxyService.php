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
     * Array of supported namespaces
     *
     * @var array
     */
    protected $namespaces = array();
    
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
     * Stack of commands
     * 
     * @var array
     */
    private $commandStack = array();
    
    /**
     * Invoke command
     * 
     * @var CommandInterface $command
     */
    public function __invoke(CommandInterface $command)
    {
        $ns = $command->getParam('ns', $command->getParam(0, '*'));
        $command->setParam('ns', $ns);
        
        // Push to command stack
        $this->commandStack[] = $command;
        
        $this->assertNamespace($ns);
        
        // Proxy operation to local method
        switch ($command->getOperation()) {
            case 'getNamespaces':
                $response = $this->namespaces;
                break;
            case 'exists':
            case 'remove':
                $response = call_user_func_array(
                    [$this, $command->getOperation()], 
                    $this->resolveArgs($command, ['ns', 'resourceId']));
                break;
            case 'findBy':
            case 'findManyBy':
                $args = $this->resolveArgs($command, ['ns', 'property', 'value', 'specs']);
                
                $response = call_user_func_array(
                    [$this, $command->getOperation()],
                    $args);
                
                // Map response parameters
                if ($command->getOperation() === 'findMany') {
                    foreach ($response as $key => &$value) {
                        $this->mapResponse($value, $args['specs']);
                    }
                } else {
                    $this->mapResponse($response, $args['specs']);
                }
                
                break;
            case 'create':
                $response = call_user_func_array(
                    [$this, $command->getOperation()],
                    $this->resolveArgs($command, ['ns', 'specs']));
                    break;
            case 'update':
                $response = call_user_func_array(
                    [$this, $command->getOperation()],
                    $this->resolveArgs($command, ['ns', 'resourceId', 'specs']));
                break;
        }
        
        // Pop from command stack
        array_pop($this->commandStack);
        
        return $response;
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
                $arguments[$arg] = $this->mapToTargetProperty($arguments[$arg]);
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
     * @param CommandInterface $command
     * @param array $params
     * @param string $operation
     * @param string $service
     */
    protected function proxy($params = null, $operation = null, $service = null)
    {
        if ($service === null) {
            
            if (!$this->getServiceName()) {
                throw new \RuntimeException('Proxy service is not defined');
            }
            
            $service = $this->getServiceName();
        }
        
        $command = $this->getCommand();
        $command->setService($service);
        
        if ($operation !== null) {
            $command->setOperation($operation);
        }
        
        if ($params !== null) {
            $command->setParams($params);
        }
        
        return $this->getServiceBroker()->dispatch($command)->first();
    }
    
    /**
     * Retrieve service name
     * 
     * @return string
     */
    protected function getServiceName()
    {
        return $this->defaultService;
    }
    
    /**
     * Retrieve spec map
     * 
     * @return array
     */
    protected function getSpecMap()
    {
        return $this->specMap;
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
                $mapped = $this->mapToTargetProperty($key);
                
                if ($mapped !== false) {
                    $specs[$mapped] = $value;
                }
                
                if ($mapped !== $key) {
                    unset($specs[$key]);
                }
            }
            
            return $specs;
        } elseif (is_string($specs)) {
            return $this->mapToTargetProperty($specs);
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
                
                $resourceSpec = $this->mapToResourceProperty($key);
                
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
     * Map property
     * 
     * @return string|boolean New spec name, or false if spec is not supported
     */
    protected function mapToTargetProperty($resourceProperty)
    {
        $map = $this->getSpecMap();
        
        if (array_key_exists($resourceProperty, $map)) {
            return $map[$resourceProperty];
        } else {
            return $resourceProperty;
        }
    }
    
    /**
     * Map target property to resource property
     */
    protected function mapToResourceProperty($targetProperty)
    {
        $map = $this->getSpecMap();
        return array_search($targetProperty, $map);
    }
    
    /**
     * Assert that namespace is supported
     * 
     * @param string $ns
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
     * Retrieve current (latest) command from the command stack
     * 
     * @return CommandInterface
     */
    protected function getCommand()
    {
        return $this->commandStack[sizeof($this->commandStack)-1];
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